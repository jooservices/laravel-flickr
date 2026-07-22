<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Support;

use JOOservices\LaravelFlickr\RateLimit\Permit;
use JOOservices\LaravelFlickr\RateLimit\RateLimitStatus;
use JOOservices\LaravelFlickr\RateLimit\RequestLimiterInterface;
use RuntimeException;

final class FixedPermitLimiter implements RequestLimiterInterface
{
    public function __construct(private readonly Permit $permit) {}

    public function acquire(string $connectionKey): Permit
    {
        return $this->permit;
    }

    public function triggerCooldown(string $connectionKey, ?int $seconds = null): void {}

    public function status(string $connectionKey, bool $fresh = true): RateLimitStatus
    {
        throw new RuntimeException('not used');
    }
}
