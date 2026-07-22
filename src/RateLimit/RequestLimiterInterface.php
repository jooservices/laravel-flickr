<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\RateLimit;

interface RequestLimiterInterface
{
    public function acquire(string $connectionKey): Permit;

    public function triggerCooldown(string $connectionKey, ?int $seconds = null): void;

    /**
     * Read-only status.
     *
     * When `$fresh` is false, prefer the in-memory snapshot from the most recent
     * {@see acquire()} for this connection key (zero Redis round-trips). Falls back
     * to a live Redis read when no snapshot exists.
     */
    public function status(string $connectionKey, bool $fresh = true): RateLimitStatus;
}
