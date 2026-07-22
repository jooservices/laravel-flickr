<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\RateLimit;

use Carbon\Carbon;

final class NullRequestLimiter implements RequestLimiterInterface
{
    public function acquire(string $connectionKey): Permit
    {
        return new Permit(true, remaining: PHP_INT_MAX, limit: PHP_INT_MAX);
    }

    public function triggerCooldown(string $connectionKey, ?int $seconds = null): void {}

    public function status(string $connectionKey, bool $fresh = true): RateLimitStatus
    {
        return new RateLimitStatus(
            remaining: PHP_INT_MAX,
            limit: PHP_INT_MAX,
            windowResetsAt: Carbon::now()->addHour(),
            inCooldown: false,
            cooldownExpiresAt: null,
            nextAllowedAt: null,
        );
    }
}
