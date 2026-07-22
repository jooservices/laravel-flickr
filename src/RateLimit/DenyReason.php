<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\RateLimit;

enum DenyReason: string
{
    case Cooldown = 'cooldown';
    case MinGap = 'min_gap';
    case HourlyQuota = 'hourly_quota';

    public function label(): string
    {
        return match ($this) {
            self::Cooldown => 'cooldown',
            self::MinGap => 'minimum gap',
            self::HourlyQuota => 'hourly quota',
        };
    }
}
