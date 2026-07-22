<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Support;

use JOOservices\LaravelFlickr\Support\RateLimitConnectionKey;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class RateLimitConnectionKeyTest extends TestCase
{
    #[Test]
    public function derives_stable_sha256_without_embedding_the_api_key(): void
    {
        $key = 'live-api-key-secret';
        $derived = RateLimitConnectionKey::fromApiKey($key);

        $this->assertSame(hash('sha256', $key), $derived);
        $this->assertStringNotContainsString($key, $derived);
        $this->assertSame(64, strlen($derived));
    }
}
