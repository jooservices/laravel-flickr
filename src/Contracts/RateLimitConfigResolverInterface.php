<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Contracts;

interface RateLimitConfigResolverInterface
{
    public function enabled(): bool;

    public function maxRequestsPerHour(): int;

    public function minGapMilliseconds(): int;

    public function cooldownSeconds(): int;

    public function keyPrefix(): string;

    public function warningThresholdPercent(): int;
}
