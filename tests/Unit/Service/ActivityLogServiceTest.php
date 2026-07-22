<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Service;

use JOOservices\LaravelFlickr\Service\ActivityLogService;
use JOOservices\LaravelFlickr\Tests\TestCase;
use JOOservices\LaravelLogging\Models\ActivityLogRecord;
use PHPUnit\Framework\Attributes\Test;

final class ActivityLogServiceTest extends TestCase
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
        ActivityLogRecord::query()->create([
            'uuid' => fake()->uuid(),
            'type' => 'activity',
            'adapter' => 'mongo',
            'action' => 'flickr.call.completed',
            'level' => 'info',
            'message' => fake()->sentence(),
            'properties' => [],
            'context' => [],
            'changes' => [],
            'occurred_at' => now(),
        ]);
        ActivityLogRecord::query()->create([
            'uuid' => fake()->uuid(),
            'type' => 'activity',
            'adapter' => 'mongo',
            'action' => 'flickr.oauth.completed',
            'level' => 'info',
            'message' => fake()->sentence(),
            'properties' => [],
            'context' => [],
            'changes' => [],
            'occurred_at' => now(),
        ]);

        $rows = app(ActivityLogService::class)
            ->filter(['action' => 'flickr.call.completed'])
            ->orderBy(['occurred_at' => 'desc'])
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame('flickr.call.completed', $rows->first()->action);
    }
}
