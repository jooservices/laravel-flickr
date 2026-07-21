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
        $lastKey = $prefix.':'.$connectionKey.':last';
        $last = Redis::get($lastKey);
        $lastMilliseconds = $this->integerValue($last);
        if ($lastMilliseconds !== null && $now - $lastMilliseconds < $gapMs) {
            $retryAfter = max(1, (int) ceil(($gapMs - ($now - $lastMilliseconds)) / 1000));

            return new Permit(false, $retryAfter, DenyReason::MinGap);
        }

        $window = max(1, $this->integerConfig('window_seconds', 3600));
        $max = max(1, $this->integerConfig('max_requests_per_hour', 3500));
        $windowKey = $prefix.':'.$connectionKey.':window';
        Redis::command('ZREMRANGEBYSCORE', [$windowKey, '0', (string) ($now - ($window * 1000))]);
        if (($this->integerValue(Redis::command('ZCARD', [$windowKey])) ?? 0) >= $max) {
            return new Permit(false, $window, DenyReason::HourlyQuota);
        }

        Redis::multi();
        Redis::setex($lastKey, max(1, (int) ceil($gapMs / 1000) + 1), (string) $now);
        Redis::command('ZADD', [$windowKey, (string) $now, $now.'-'.bin2hex(random_bytes(8))]);
        Redis::expire($windowKey, $window + 60);
        Redis::exec();

        return new Permit(true);
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
}
