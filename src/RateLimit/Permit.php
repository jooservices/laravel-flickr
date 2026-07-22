<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\RateLimit;

final readonly class Permit
{
    public function __construct(
        public bool $acquired,
        public int $retryAfterSeconds = 0,
        public ?DenyReason $reason = null,
        public ?int $remaining = null,
        public ?int $limit = null,
    ) {}
}
