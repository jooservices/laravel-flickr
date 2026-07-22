<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\RateLimit;

use Carbon\CarbonInterface;

final readonly class RateLimitStatus
{
    public function __construct(
        public int $remaining,
        public int $limit,
        public CarbonInterface $windowResetsAt,
        public bool $inCooldown,
        public ?CarbonInterface $cooldownExpiresAt,
        public ?CarbonInterface $nextAllowedAt,
    ) {}
}
