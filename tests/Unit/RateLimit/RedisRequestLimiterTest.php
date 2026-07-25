<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\RateLimit;

use Illuminate\Support\Facades\Redis;
use JOOservices\LaravelConfig\Facades\Config;
use JOOservices\LaravelFlickr\Contracts\RateLimitConfigResolverInterface;
use JOOservices\LaravelFlickr\RateLimit\DenyReason;
use JOOservices\LaravelFlickr\RateLimit\RedisRequestLimiter;
use JOOservices\LaravelFlickr\Support\LaravelConfigRateLimitResolver;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class RedisRequestLimiterTest extends TestCase
{
    private string $connectionKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresRedis();
        $this->connectionKey = 'test-'.fake()->sha1();
        $this->seedRateLimitConfig();
    }

    #[Test]
    public function acquire_claims_slots_and_exposes_remaining_snapshot(): void
    {
        $this->seedRateLimitConfig(maxPerHour: 5);
        $limiter = $this->limiter();

        $permit = $limiter->acquire($this->connectionKey);

        $this->assertTrue($permit->acquired);
        $this->assertSame(4, $permit->remaining);
        $this->assertSame(5, $permit->limit);

        $snapshot = $limiter->status($this->connectionKey, fresh: false);
        $this->assertSame(4, $snapshot->remaining);
        $this->assertSame(5, $snapshot->limit);
    }

    #[Test]
    public function acquire_denies_when_hourly_quota_is_exhausted(): void
    {
        $this->seedRateLimitConfig(maxPerHour: 1);
        $limiter = $this->limiter();

        $this->assertTrue($limiter->acquire($this->connectionKey)->acquired);

        $denied = $limiter->acquire($this->connectionKey);

        $this->assertFalse($denied->acquired);
        $this->assertSame(DenyReason::HourlyQuota, $denied->reason);
        $this->assertSame(0, $denied->remaining);
    }

    #[Test]
    public function acquire_denies_while_cooldown_is_active(): void
    {
        $limiter = $this->limiter();
        $limiter->triggerCooldown($this->connectionKey, 90);

        $permit = $limiter->acquire($this->connectionKey);

        $this->assertFalse($permit->acquired);
        $this->assertSame(DenyReason::Cooldown, $permit->reason);
        $this->assertGreaterThan(0, $permit->retryAfterSeconds);

        $snapshot = $limiter->status($this->connectionKey, fresh: false);
        $this->assertTrue($snapshot->inCooldown);
    }

    #[Test]
    public function acquire_denies_when_min_gap_is_violated(): void
    {
        $this->seedRateLimitConfig(maxPerHour: 10, minGapMs: 5_000);
        $limiter = $this->limiter();

        $this->assertTrue($limiter->acquire($this->connectionKey)->acquired);

        $denied = $limiter->acquire($this->connectionKey);

        $this->assertFalse($denied->acquired);
        $this->assertSame(DenyReason::MinGap, $denied->reason);
        $this->assertGreaterThan(0, $denied->retryAfterSeconds);
    }

    #[Test]
    public function status_fresh_reads_live_redis_state(): void
    {
        $this->seedRateLimitConfig(maxPerHour: 3);
        $limiter = $this->limiter();

        $this->assertTrue($limiter->acquire($this->connectionKey)->acquired);

        $fresh = $limiter->status($this->connectionKey, fresh: true);
        $this->assertSame(2, $fresh->remaining);
        $this->assertSame(3, $fresh->limit);
        $this->assertFalse($fresh->inCooldown);
    }

    #[Test]
    public function disabled_limiter_bypasses_redis_without_rebinding(): void
    {
        $this->seedRateLimitConfig(maxPerHour: 1);
        $limiter = $this->limiter();
        $this->assertTrue($limiter->acquire($this->connectionKey)->acquired);

        Config::fake([
            'flickr' => [
                'rate_limit_enabled' => false,
                'rate_limit_max_requests_per_hour' => 1,
                'rate_limit_min_gap_ms' => 0,
                'rate_limit_cooldown_seconds' => 60,
                'rate_limit_key_prefix' => 'laravel-flickr-test:'.getmypid(),
                'rate_limit_warning_threshold_percent' => 80,
            ],
        ]);

        $this->app->instance(RateLimitConfigResolverInterface::class, new LaravelConfigRateLimitResolver());
        $live = new RedisRequestLimiter(app(RateLimitConfigResolverInterface::class));

        $this->assertTrue($live->acquire($this->connectionKey)->acquired);
        $this->assertSame(PHP_INT_MAX, $live->status($this->connectionKey)->remaining);
    }

    private function seedRateLimitConfig(int $maxPerHour = 3300, int $minGapMs = 0): void
    {
        Config::fake([
            'flickr' => [
                'rate_limit_enabled' => true,
                'rate_limit_max_requests_per_hour' => $maxPerHour,
                'rate_limit_min_gap_ms' => $minGapMs,
                'rate_limit_cooldown_seconds' => 60,
                'rate_limit_key_prefix' => 'laravel-flickr-test:'.getmypid(),
                'rate_limit_warning_threshold_percent' => 80,
            ],
        ]);
    }

    private function limiter(): RedisRequestLimiter
    {
        $this->app->instance(RateLimitConfigResolverInterface::class, new LaravelConfigRateLimitResolver());

        return new RedisRequestLimiter(app(RateLimitConfigResolverInterface::class));
    }

    #[Test]
    public function acquire_accepts_non_array_eval_payload(): void
    {
        $this->seedRateLimitConfig(maxPerHour: 9, minGapMs: 0);
        $prefix = 'laravel-flickr-test:'.getmypid();

        Redis::shouldReceive('get')
            ->once()
            ->andReturn(null);
        $connection = \Mockery::mock();
        $connection->shouldReceive('command')
            ->once()
            ->withArgs(function (string $cmd, array $args): bool {
                return $cmd === 'eval' && is_string($args[0] ?? null);
            })
            ->andReturn(1); // non-array success shape from some clients
        Redis::shouldReceive('connection')->andReturn($connection);

        $permit = $this->limiter()->acquire($this->connectionKey);
        $this->assertTrue($permit->acquired);
        $this->assertSame(8, $permit->remaining);
        $this->assertSame(9, $permit->limit);
        unset($prefix);
    }

    #[Test]
    public function status_uses_oldest_window_member_score_when_present(): void
    {
        $this->seedRateLimitConfig(maxPerHour: 10, minGapMs: 0);
        $prefix = 'laravel-flickr-test:'.getmypid();
        $windowKey = $prefix.':'.$this->connectionKey.':window';
        $lastKey = $prefix.':'.$this->connectionKey.':last';
        $cooldownKey = $prefix.':'.$this->connectionKey.':cooldown';

        $pipe = \Mockery::mock();
        $pipe->shouldReceive('zcard')->once()->with($windowKey)->andReturnSelf();
        $pipe->shouldReceive('get')->once()->with($cooldownKey)->andReturnSelf();
        $pipe->shouldReceive('zrange')->once()->with($windowKey, 0, 0, ['WITHSCORES' => true])->andReturnSelf();
        $pipe->shouldReceive('get')->once()->with($lastKey)->andReturnSelf();

        $connection = \Mockery::mock();
        $connection->shouldReceive('command')
            ->once()
            ->with('zremrangebyscore', \Mockery::type('array'))
            ->andReturn(0);
        $connection->shouldReceive('pipeline')
            ->once()
            ->andReturnUsing(function (callable $callback) use ($pipe): array {
                $callback($pipe);

                return [
                    2,
                    null,
                    ['member-1', '1700000000000'],
                    null,
                ];
            });
        Redis::shouldReceive('connection')->andReturn($connection);

        $status = $this->limiter()->status($this->connectionKey, fresh: true);
        $this->assertSame(8, $status->remaining);
        $this->assertNotNull($status->windowResetsAt);
    }

    #[Test]
    public function status_normalizes_associative_zrange_withscores(): void
    {
        $this->seedRateLimitConfig(maxPerHour: 10, minGapMs: 0);
        $prefix = 'laravel-flickr-test:'.getmypid();
        $windowKey = $prefix.':'.$this->connectionKey.':window';

        $connection = \Mockery::mock();
        $connection->shouldReceive('command')
            ->once()
            ->with('zremrangebyscore', \Mockery::type('array'))
            ->andReturn(0);
        $connection->shouldReceive('pipeline')
            ->once()
            ->andReturnUsing(function (callable $callback): array {
                $pipe = \Mockery::mock();
                $pipe->shouldReceive('zcard')->andReturnSelf();
                $pipe->shouldReceive('get')->andReturnSelf();
                $pipe->shouldReceive('zrange')->andReturnSelf();
                $callback($pipe);

                return [
                    1,
                    null,
                    ['member-1' => '1700000000000'],
                    null,
                ];
            });
        Redis::shouldReceive('connection')->andReturn($connection);

        $status = $this->limiter()->status($this->connectionKey, fresh: true);
        $this->assertSame(9, $status->remaining);
        $this->assertNotNull($status->windowResetsAt);
        unset($windowKey, $prefix);
    }

    #[Test]
    public function status_reports_gap_cooldown_and_exhausted_window_paths(): void
    {
        $this->seedRateLimitConfig(maxPerHour: 1, minGapMs: 5000);
        $limiter = $this->limiter();
        $key = 'edge-'.fake()->sha1();

        $this->assertTrue($limiter->acquire($key)->acquired);
        $this->assertNotNull($limiter->status($key, fresh: true)->nextAllowedAt);

        $limiter->triggerCooldown($key, 30);
        $statusCd = $limiter->status($key, fresh: true);
        $this->assertTrue($statusCd->inCooldown);
        $this->assertNotNull($statusCd->nextAllowedAt);

        $ref = new \ReflectionClass($limiter);
        $prop = $ref->getProperty('lastAcquireSnapshots');
        $prop->setAccessible(true);
        $map = [];
        for ($i = 0; $i < 64; $i++) {
            $map['k'.$i] = $statusCd;
        }
        $prop->setValue($limiter, $map);
        $limiter->acquire('new-key-'.fake()->sha1());

        $int = $ref->getMethod('integerValue');
        $int->setAccessible(true);
        $this->assertSame(3, $int->invoke($limiter, 3.7));
        $this->assertNull($int->invoke($limiter, []));
    }

    #[Test]
    public function trigger_cooldown_is_noop_when_disabled(): void
    {
        Config::fake([
            'flickr' => [
                'rate_limit_enabled' => false,
                'rate_limit_key_prefix' => 'edge-off:'.getmypid(),
                'default_connection' => 'default',
                'queue_name' => 'flickr',
            ],
        ]);
        $limiter = new RedisRequestLimiter(new LaravelConfigRateLimitResolver());
        $limiter->triggerCooldown('x', null);
        $this->assertSame(PHP_INT_MAX, $limiter->status('x')->remaining);
    }

    #[Test]
    public function status_next_allowed_when_hourly_remaining_is_zero(): void
    {
        $this->seedRateLimitConfig(maxPerHour: 1, minGapMs: 0);
        $limiter = $this->limiter();
        $key = 'k-'.fake()->sha1();
        $this->assertTrue($limiter->acquire($key)->acquired);
        $this->assertFalse($limiter->acquire($key)->acquired);

        $status = $limiter->status($key, fresh: true);
        $this->assertSame(0, $status->remaining);
        $this->assertNotNull($status->nextAllowedAt);
    }
}
