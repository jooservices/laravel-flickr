<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\RateLimit;

use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;
use JOOservices\LaravelFlickr\Contracts\RateLimitConfigResolverInterface;

/** Redis-backed, synchronous limiter. Hosts may bind another implementation. */
final class RedisRequestLimiter implements RequestLimiterInterface
{
    private const WINDOW_SECONDS = 3600;

    private const int SNAPSHOT_CAP = 64;

    /** @var array<string, RateLimitStatus> */
    private array $lastAcquireSnapshots = [];

    public function __construct(
        private readonly RateLimitConfigResolverInterface $rateLimitConfig,
    ) {}

    public function acquire(string $connectionKey): Permit
    {
        if (! $this->rateLimitConfig->enabled()) {
            return new Permit(true, remaining: PHP_INT_MAX, limit: PHP_INT_MAX);
        }

        $now = (int) floor(microtime(true) * 1000);
        $prefix = $this->rateLimitConfig->keyPrefix();
        $cooldown = $prefix.':'.$connectionKey.':cooldown';
        $until = Redis::get($cooldown);
        $untilSeconds = $this->integerValue($until);
        $max = max(1, $this->rateLimitConfig->maxRequestsPerHour());

        if ($untilSeconds !== null && $untilSeconds > time()) {
            $permit = new Permit(false, max(1, $untilSeconds - time()), DenyReason::Cooldown, remaining: 0, limit: $max);
            $this->rememberFromPermit($connectionKey, $permit, inCooldown: true, cooldownUntil: $untilSeconds);

            return $permit;
        }

        $gapMs = max(0, $this->rateLimitConfig->minGapMilliseconds());
        $window = self::WINDOW_SECONDS;
        $lastKey = $prefix.':'.$connectionKey.':last';
        $windowKey = $prefix.':'.$connectionKey.':window';
        $result = Redis::connection()->command('eval', [$this->claimScript(), [
            $lastKey, $windowKey, (string) $now, (string) $gapMs, (string) $window,
            (string) $max, $now.'-'.bin2hex(random_bytes(8)),
        ], 2]);

        if (is_array($result) && ($this->integerValue($result[0] ?? null) ?? 0) === 0) {
            $reason = ($result[1] ?? '') === 'hourly' ? DenyReason::HourlyQuota : DenyReason::MinGap;
            $remaining = $reason === DenyReason::HourlyQuota ? 0 : null;
            $permit = new Permit(
                false,
                max(1, $this->integerValue($result[2] ?? null) ?? $window),
                $reason,
                remaining: $remaining,
                limit: $max,
            );
            $this->rememberFromPermit($connectionKey, $permit);

            return $permit;
        }

        $remaining = is_array($result)
            ? ($this->integerValue($result[1] ?? null) ?? max(0, $max - 1))
            : max(0, $max - 1);
        $limit = is_array($result)
            ? ($this->integerValue($result[2] ?? null) ?? $max)
            : $max;
        $permit = new Permit(true, remaining: $remaining, limit: $limit);
        $this->rememberFromPermit($connectionKey, $permit);

        return $permit;
    }

    public function triggerCooldown(string $connectionKey, ?int $seconds = null): void
    {
        if (! $this->rateLimitConfig->enabled()) {
            return;
        }

        $seconds ??= max(1, $this->rateLimitConfig->cooldownSeconds());
        $prefix = $this->rateLimitConfig->keyPrefix();
        Redis::setex($prefix.':'.$connectionKey.':cooldown', $seconds, (string) (time() + $seconds));
    }

    public function status(string $connectionKey, bool $fresh = true): RateLimitStatus
    {
        if (! $this->rateLimitConfig->enabled()) {
            return new RateLimitStatus(
                remaining: PHP_INT_MAX,
                limit: PHP_INT_MAX,
                windowResetsAt: Carbon::now()->addHour(),
                inCooldown: false,
                cooldownExpiresAt: null,
                nextAllowedAt: null,
            );
        }

        if (! $fresh && isset($this->lastAcquireSnapshots[$connectionKey])) {
            return $this->lastAcquireSnapshots[$connectionKey];
        }

        $now = (int) floor(microtime(true) * 1000);
        $prefix = $this->rateLimitConfig->keyPrefix();
        $window = self::WINDOW_SECONDS;
        $max = max(1, $this->rateLimitConfig->maxRequestsPerHour());
        $gapMs = max(0, $this->rateLimitConfig->minGapMilliseconds());
        $windowKey = $prefix.':'.$connectionKey.':window';
        $lastKey = $prefix.':'.$connectionKey.':last';
        $cooldownKey = $prefix.':'.$connectionKey.':cooldown';

        Redis::connection()->command('zremrangebyscore', [$windowKey, '0', (string) ($now - ($window * 1000))]);
        $used = $this->integerValue(Redis::connection()->command('zcard', [$windowKey])) ?? 0;
        $remaining = max(0, $max - $used);

        $cooldownUntil = $this->integerValue(Redis::get($cooldownKey));
        $inCooldown = $cooldownUntil !== null && $cooldownUntil > time();
        $cooldownExpiresAt = $inCooldown ? Carbon::createFromTimestamp($cooldownUntil) : null;

        $oldest = Redis::connection()->command('zrange', [$windowKey, 0, 0, 'WITHSCORES']);
        $windowResetsAt = Carbon::now()->addSeconds($window);
        if (is_array($oldest) && isset($oldest[1]) && is_numeric($oldest[1])) {
            $windowResetsAt = Carbon::createFromTimestampMs(((int) $oldest[1]) + ($window * 1000));
        }

        $nextAllowedAt = null;
        if ($inCooldown) {
            $nextAllowedAt = $cooldownExpiresAt;
        } else {
            $last = $this->integerValue(Redis::get($lastKey));
            if ($last !== null && ($now - $last) < $gapMs) {
                $nextAllowedAt = Carbon::createFromTimestampMs($last + $gapMs);
            } elseif ($remaining === 0) {
                $nextAllowedAt = $windowResetsAt;
            }
        }

        return new RateLimitStatus(
            remaining: $remaining,
            limit: $max,
            windowResetsAt: $windowResetsAt,
            inCooldown: $inCooldown,
            cooldownExpiresAt: $cooldownExpiresAt,
            nextAllowedAt: $nextAllowedAt,
        );
    }

    private function rememberFromPermit(
        string $connectionKey,
        Permit $permit,
        bool $inCooldown = false,
        ?int $cooldownUntil = null,
    ): void {
        $limit = $permit->limit ?? max(1, $this->rateLimitConfig->maxRequestsPerHour());
        $remaining = $permit->remaining ?? ($permit->acquired ? max(0, $limit - 1) : 0);

        if (count($this->lastAcquireSnapshots) >= self::SNAPSHOT_CAP && ! isset($this->lastAcquireSnapshots[$connectionKey])) {
            // Drop oldest entry (insertion order) to bound memory in long-lived workers.
            unset($this->lastAcquireSnapshots[array_key_first($this->lastAcquireSnapshots)]);
        }

        $this->lastAcquireSnapshots[$connectionKey] = new RateLimitStatus(
            remaining: $remaining,
            limit: $limit,
            windowResetsAt: Carbon::now()->addSeconds(self::WINDOW_SECONDS),
            inCooldown: $inCooldown,
            cooldownExpiresAt: $inCooldown && $cooldownUntil !== null
                ? Carbon::createFromTimestamp($cooldownUntil)
                : null,
            nextAllowedAt: $permit->acquired ? null : Carbon::now()->addSeconds(max(1, $permit->retryAfterSeconds)),
        );
    }

    private function integerValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        return is_string($value) && is_numeric($value) ? (int) $value : null;
    }

    private function claimScript(): string
    {
        return <<<'LUA'
local lastKey, windowKey = KEYS[1], KEYS[2]
local now, gap, window, max = tonumber(ARGV[1]), tonumber(ARGV[2]), tonumber(ARGV[3]), tonumber(ARGV[4])
redis.call('ZREMRANGEBYSCORE', windowKey, '0', tostring(now - (window * 1000)))
local last = tonumber(redis.call('GET', lastKey) or '')
if last and now - last < gap then return {0, 'gap', math.ceil((gap - (now - last)) / 1000)} end
if redis.call('ZCARD', windowKey) >= max then return {0, 'hourly', window} end
redis.call('SETEX', lastKey, math.max(1, math.ceil(gap / 1000) + 1), tostring(now))
redis.call('ZADD', windowKey, now, ARGV[5])
redis.call('EXPIRE', windowKey, window + 60)
local remaining = max - redis.call('ZCARD', windowKey)
return {1, remaining, max}
LUA;
    }
}
