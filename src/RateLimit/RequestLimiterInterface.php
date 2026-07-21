<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr\RateLimit;

interface RequestLimiterInterface
{
    public function acquire(string $connectionKey): Permit;

    public function triggerCooldown(string $connectionKey, ?int $seconds = null): void;
}
