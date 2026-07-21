<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr\RateLimit;

final readonly class Permit
{
    public function __construct(
        public bool $acquired,
        public int $retryAfterSeconds = 0,
        public ?DenyReason $reason = null,
    ) {}
}
