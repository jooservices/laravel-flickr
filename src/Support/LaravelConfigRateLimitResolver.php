<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Support;

use JOOservices\LaravelFlickr\Contracts\RateLimitConfigResolverInterface;

/**
 * Reads flat `flickr.*` keys from laravel-config (group.key paths only).
 */
final class LaravelConfigRateLimitResolver implements RateLimitConfigResolverInterface
{
    public function enabled(): bool
    {
        return LaravelConfigValue::bool('flickr.rate_limit_enabled', true);
    }

    public function maxRequestsPerHour(): int
    {
        return LaravelConfigValue::int('flickr.rate_limit_max_requests_per_hour', 3300);
    }

    public function minGapMilliseconds(): int
    {
        return LaravelConfigValue::int('flickr.rate_limit_min_gap_ms', 333);
    }

    public function cooldownSeconds(): int
    {
        return LaravelConfigValue::int('flickr.rate_limit_cooldown_seconds', 3600);
    }

    public function keyPrefix(): string
    {
        return LaravelConfigValue::string('flickr.rate_limit_key_prefix', 'laravel-flickr:req');
    }

    public function warningThresholdPercent(): int
    {
        return LaravelConfigValue::int('flickr.rate_limit_warning_threshold_percent', 80);
    }
}
