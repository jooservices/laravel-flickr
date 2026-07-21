<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr\RateLimit;

final class NullRequestLimiter implements RequestLimiterInterface
{
    public function acquire(string $connectionKey): Permit
    {
        return new Permit(true);
    }

    public function triggerCooldown(string $connectionKey, ?int $seconds = null): void {}
}
