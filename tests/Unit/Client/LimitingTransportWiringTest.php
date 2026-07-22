<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Client;

use Illuminate\Support\Facades\Event;
use JOOservices\Client\Client\ClientBuilder;
use JOOservices\LaravelConfig\Facades\Config;
use JOOservices\LaravelFlickr\Client\FlickrClientFactory;
use JOOservices\LaravelFlickr\Dto\AppCredentials;
use JOOservices\LaravelFlickr\Events\FlickrRateLimitApproaching;
use JOOservices\LaravelFlickr\Exceptions\RateLimitedException;
use JOOservices\LaravelFlickr\RateLimit\DenyReason;
use JOOservices\LaravelFlickr\RateLimit\RequestLimiterInterface;
use JOOservices\LaravelFlickr\Support\RateLimitConnectionKey;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class LimitingTransportWiringTest extends TestCase
{
    private string $apiKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresRedis();

        $this->apiKey = 'wiring-'.fake()->sha1();
        Config::fake([
            'flickr' => [
                'default_connection' => 'default',
                'rate_limit_enabled' => true,
                'rate_limit_max_requests_per_hour' => 1,
                'rate_limit_min_gap_ms' => 0,
                'rate_limit_cooldown_seconds' => 3600,
                'rate_limit_key_prefix' => 'laravel-flickr-test:'.getmypid(),
                'rate_limit_warning_threshold_percent' => 80,
                'queue_name' => 'flickr',
                'logging_enabled' => false,
                'events_enabled' => false,
            ],
        ]);
        $this->app->forgetInstance(RequestLimiterInterface::class);
        $this->app->forgetInstance(FlickrClientFactory::class);
        Event::fake([FlickrRateLimitApproaching::class]);
    }

    #[Test]
    public function factory_enforces_rate_limits_on_live_client_path(): void
    {
        $this->fakeFlickrResponses([
            ['method' => ['_content' => 'flickr.test.echo']],
            ['method' => ['_content' => 'flickr.test.echo']],
        ]);

        $client = app(FlickrClientFactory::class)->anonymous(new AppCredentials($this->apiKey, 'secret'));

        $first = $client->raw()->call('flickr.test.echo', ['foo' => 'bar']);
        $this->assertTrue($first->ok);

        try {
            $client->raw()->call('flickr.test.echo', ['foo' => 'baz']);
            $this->fail('Expected RateLimitedException on second call');
        } catch (RateLimitedException $e) {
            $this->assertSame(DenyReason::HourlyQuota, $e->denyReason);
        }

        $this->assertCount(1, ClientBuilder::recorded());
    }

    #[Test]
    public function approaching_threshold_dispatches_warning_event_once_on_crossing(): void
    {
        Config::fake([
            'flickr' => [
                'default_connection' => 'default',
                'rate_limit_enabled' => true,
                'rate_limit_max_requests_per_hour' => 5,
                'rate_limit_min_gap_ms' => 0,
                'rate_limit_cooldown_seconds' => 3600,
                'rate_limit_key_prefix' => 'laravel-flickr-test:'.getmypid(),
                'rate_limit_warning_threshold_percent' => 20,
                'queue_name' => 'flickr',
                'logging_enabled' => false,
                'events_enabled' => false,
            ],
        ]);
        $this->app->forgetInstance(RequestLimiterInterface::class);
        $this->app->forgetInstance(FlickrClientFactory::class);

        $this->fakeFlickrResponses([
            ['method' => ['_content' => 'flickr.test.echo']],
            ['method' => ['_content' => 'flickr.test.echo']],
        ]);

        $client = app(FlickrClientFactory::class)->anonymous(new AppCredentials($this->apiKey, 'secret'));
        $client->raw()->call('flickr.test.echo', ['foo' => 'bar']);

        Event::assertDispatched(FlickrRateLimitApproaching::class, function (FlickrRateLimitApproaching $event): bool {
            return $event->connectionKey === RateLimitConnectionKey::fromApiKey($this->apiKey)
                && $event->remaining === 4
                && $event->limit === 5
                && $event->percentUsed >= 20.0;
        });

        // Second call is still over threshold — must not fire again (transition only).
        $client->raw()->call('flickr.test.echo', ['foo' => 'baz']);
        Event::assertDispatchedTimes(FlickrRateLimitApproaching::class, 1);
    }
}
