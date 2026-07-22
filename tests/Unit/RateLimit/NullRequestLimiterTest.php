<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\RateLimit;

use JOOservices\LaravelFlickr\RateLimit\NullRequestLimiter;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class NullRequestLimiterTest extends TestCase
{
    #[Test]
    public function acquire_always_grants_unlimited_permit(): void
    {
        $limiter = new NullRequestLimiter();
        $permit = $limiter->acquire('any-key');

        $this->assertTrue($permit->acquired);
        $this->assertSame(PHP_INT_MAX, $permit->remaining);
        $this->assertSame(PHP_INT_MAX, $permit->limit);
        $this->assertNull($permit->reason);
    }

    #[Test]
    public function trigger_cooldown_is_a_noop(): void
    {
        $limiter = new NullRequestLimiter();
        $limiter->triggerCooldown('any-key', 3600);

        $status = $limiter->status('any-key');
        $this->assertFalse($status->inCooldown);
        $this->assertNull($status->cooldownExpiresAt);
    }

    #[Test]
    public function status_reports_unlimited_capacity(): void
    {
        $limiter = new NullRequestLimiter();
        $status = $limiter->status('any-key');

        $this->assertSame(PHP_INT_MAX, $status->remaining);
        $this->assertSame(PHP_INT_MAX, $status->limit);
        $this->assertFalse($status->inCooldown);
        $this->assertNull($status->nextAllowedAt);
    }
}
