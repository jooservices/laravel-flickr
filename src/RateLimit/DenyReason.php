<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr\RateLimit;

enum DenyReason: string
{
    case Cooldown = 'cooldown';
    case MinGap = 'min_gap';
    case HourlyQuota = 'hourly_quota';
}
