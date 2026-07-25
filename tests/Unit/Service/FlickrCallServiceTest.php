<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Service;

use Illuminate\Support\Facades\Event;
use JOOservices\LaravelFlickr\Events\FlickrCallCompleted;
use JOOservices\LaravelFlickr\Events\FlickrCallFailed;
use JOOservices\LaravelFlickr\Events\FlickrCallStarting;
use JOOservices\LaravelFlickr\Service\FlickrCallService;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

final class FlickrCallServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresMongoDb();
        $this->clearFlickrCollections();
    }

    #[Test]
    public function execute_emits_lifecycle_events_with_correlation_id(): void
    {
        Event::fake([FlickrCallStarting::class, FlickrCallCompleted::class, FlickrCallFailed::class]);
        $this->storeApp();
        $this->fakeFlickrResponses([
            ['method' => ['_content' => 'flickr.test.echo']],
        ]);

        $response = app(FlickrCallService::class)->execute(
            'test',
            'echo',
            $this->defaultAppName,
            null,
            ['foo' => 'bar'],
            correlationId: 'corr-xyz',
        );

        $this->assertTrue($response->ok);
        Event::assertDispatched(FlickrCallStarting::class, static fn (FlickrCallStarting $e): bool => $e->correlationId === 'corr-xyz');
        Event::assertDispatched(FlickrCallCompleted::class, static fn (FlickrCallCompleted $e): bool => $e->correlationId === 'corr-xyz');
    }

    #[Test]
    public function execute_fires_failed_event_with_context(): void
    {
        Event::fake();
        $this->storeApp();
        $this->fakeFlickrResponses([]);

        try {
            app(FlickrCallService::class)->execute(
                'test',
                'echo',
                $this->defaultAppName,
                null,
                [],
                correlationId: 'fail-1',
            );
            $this->fail('expected transport failure');
        } catch (RuntimeException) {
        }

        Event::assertDispatched(FlickrCallFailed::class, static fn (FlickrCallFailed $e): bool => $e->correlationId === 'fail-1');
    }
}
