<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Exceptions;

use JOOservices\LaravelFlickr\Exceptions\LaravelFlickrRuntimeException;
use JOOservices\LaravelFlickr\Exceptions\RateLimitedException;
use JOOservices\LaravelFlickr\RateLimit\DenyReason;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class RateLimitedExceptionTest extends TestCase
{
    #[Test]
    public function it_uses_custom_message_when_provided(): void
    {
        $e = new RateLimitedException(9, DenyReason::Cooldown, 'ck', 'custom-deny');

        $this->assertSame('custom-deny', $e->getMessage());
        $this->assertInstanceOf(LaravelFlickrRuntimeException::class, $e);
        $this->assertSame('laravel-flickr', $e->packageDomain());
    }

    #[Test]
    public function it_builds_default_message_from_deny_reason(): void
    {
        $e = new RateLimitedException(5, DenyReason::MinGap, 'ck');

        $this->assertStringContainsString('min_gap', $e->getMessage());
        $this->assertStringContainsString('5', $e->getMessage());
    }
}
