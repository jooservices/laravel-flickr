<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Support;

use JOOservices\LaravelFlickr\RateLimit\Permit;
use JOOservices\LaravelFlickr\RateLimit\RateLimitStatus;
use JOOservices\LaravelFlickr\RateLimit\RequestLimiterInterface;
use RuntimeException;

final class SequencePermitLimiter implements RequestLimiterInterface
{
    /** @param list<Permit> $sequence */
    public function __construct(private array $sequence) {}

    public function acquire(string $connectionKey): Permit
    {
        return array_shift($this->sequence) ?? new Permit(true, remaining: 1, limit: 100);
    }

    public function triggerCooldown(string $connectionKey, ?int $seconds = null): void {}

    public function status(string $connectionKey, bool $fresh = true): RateLimitStatus
    {
        throw new RuntimeException('not used');
    }
}
