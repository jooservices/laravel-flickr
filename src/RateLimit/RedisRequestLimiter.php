<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr\RateLimit;

use Illuminate\Support\Facades\Redis;

/** Redis-backed, synchronous limiter. Hosts may bind another implementation. */
final class RedisRequestLimiter implements RequestLimiterInterface
{
    public function acquire(string $connectionKey): Permit
    {
        $now = (int) floor(microtime(true) * 1000);
        $prefix = $this->stringConfig('key_prefix', 'laravel-flickr:req');
        $cooldown = $prefix.':'.$connectionKey.':cooldown';
        $until = Redis::get($cooldown);
        $untilSeconds = $this->integerValue($until);
        if ($untilSeconds !== null && $untilSeconds > time()) {
            return new Permit(false, max(1, $untilSeconds - time()), DenyReason::Cooldown);
        }

        $gapMs = max(0, $this->integerConfig('min_gap_ms', 333));
        $window = max(1, $this->integerConfig('window_seconds', 3600));
        $max = max(1, $this->integerConfig('max_requests_per_hour', 3500));
        $lastKey = $prefix.':'.$connectionKey.':last';
        $windowKey = $prefix.':'.$connectionKey.':window';
        $result = Redis::connection()->command('eval', [$this->claimScript(), [
            $lastKey, $windowKey, (string) $now, (string) $gapMs, (string) $window,
            (string) $max, $now.'-'.bin2hex(random_bytes(8)),
        ], 2]);
        if (! is_array($result) || ($result[0] ?? 1) === 1) {
            return new Permit(true);
        }
        $reason = ($result[1] ?? '') === 'hourly' ? DenyReason::HourlyQuota : DenyReason::MinGap;

        return new Permit(false, max(1, $this->integerValue($result[2] ?? null) ?? $window), $reason);
    }

    public function triggerCooldown(string $connectionKey, ?int $seconds = null): void
    {
        $seconds ??= max(1, $this->integerConfig('cooldown_seconds', 3600));
        $prefix = $this->stringConfig('key_prefix', 'laravel-flickr:req');
        Redis::setex($prefix.':'.$connectionKey.':cooldown', $seconds, (string) (time() + $seconds));
    }

    private function integerConfig(string $key, int $default): int
    {
        return $this->integerValue(config("laravel-flickr.rate_limit.{$key}", $default)) ?? $default;
    }

    private function stringConfig(string $key, string $default): string
    {
        $value = config("laravel-flickr.rate_limit.{$key}", $default);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    private function integerValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
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
return {1}
LUA;
    }
}
