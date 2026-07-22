<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Events;

final class FlickrRateLimitApproaching
{
    public function __construct(
        public readonly string $connectionKey,
        public readonly int $remaining,
        public readonly int $limit,
        public readonly float $percentUsed,
    ) {}
}
