<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Events;

use JOOservices\LaravelFlickr\RateLimit\DenyReason;

final class FlickrRateLimited
{
    public function __construct(
        public readonly string $appName,
        public readonly string $namespace,
        public readonly string $method,
        public readonly ?string $nsid,
        public readonly int $retryAfterSeconds,
        public readonly DenyReason $reason,
    ) {}
}
