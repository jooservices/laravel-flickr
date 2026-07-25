<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Console;

use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class FlickrRateLimitStatusCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresMongoDb();
        $this->clearFlickrCollections();
    }

    #[Test]
    public function status_succeeds_for_registered_app(): void
    {
        $this->storeApp();

        $this->artisan('flickr:rate-limit:status')
            ->expectsOutputToContain('remaining')
            ->expectsOutputToContain((string) PHP_INT_MAX)
            ->assertSuccessful();
    }

    #[Test]
    public function status_fails_for_unknown_app(): void
    {
        $this->artisan('flickr:rate-limit:status', ['connection' => 'missing-app'])
            ->expectsOutputToContain('missing-app')
            ->assertFailed();
    }
}
