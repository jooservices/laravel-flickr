<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Service;

use JOOservices\LaravelEvents\EventSourcing\Models\StoredEvent;
use JOOservices\LaravelFlickr\Service\StoredEventService;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class StoredEventServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresMongoDb();
        $this->clearFlickrCollections();
    }

    #[Test]
    public function it_filters_and_orders_real_mongo_rows(): void
    {
        StoredEvent::query()->create([
            'event_class' => 'JOOservices\\LaravelFlickr\\Events\\FlickrCallCompleted',
            'event_name' => 'flickr.call.completed',
            'payload' => ['ok' => true],
            'metadata' => [],
            'occurred_at' => now(),
        ]);
        StoredEvent::query()->create([
            'event_class' => 'JOOservices\\LaravelFlickr\\Events\\FlickrOAuthCompleted',
            'event_name' => 'flickr.oauth.completed',
            'payload' => ['nsid' => fake()->uuid()],
            'metadata' => [],
            'occurred_at' => now(),
        ]);

        $rows = app(StoredEventService::class)
            ->filter(['event_name' => 'flickr.call.completed'])
            ->orderBy(['occurred_at' => 'asc'])
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame('flickr.call.completed', $rows->first()->event_name);
    }
}
