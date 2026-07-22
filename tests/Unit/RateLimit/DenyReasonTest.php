<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\RateLimit;

use JOOservices\LaravelFlickr\RateLimit\DenyReason;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class DenyReasonTest extends TestCase
{
    #[Test]
    public function cases_expose_values_and_labels(): void
    {
        $this->assertSame('cooldown', DenyReason::Cooldown->value);
        $this->assertSame('min_gap', DenyReason::MinGap->value);
        $this->assertSame('hourly_quota', DenyReason::HourlyQuota->value);
        $this->assertSame('cooldown', DenyReason::Cooldown->label());
        $this->assertSame('minimum gap', DenyReason::MinGap->label());
        $this->assertSame('hourly quota', DenyReason::HourlyQuota->label());
    }
}
