<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Console;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use JOOservices\LaravelFlickr\Console\Commands\FlickrDoctorCommand;
use JOOservices\LaravelFlickr\Tests\Support\FakeMongoClientForDoctor;
use JOOservices\LaravelFlickr\Tests\Support\IndexWithoutGetKey;
use JOOservices\LaravelFlickr\Tests\TestCase;
use MongoDB\Collection;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use RuntimeException;

final class FlickrDoctorCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresMongoDb();
        $this->requiresRedis();
        $this->clearFlickrCollections();
    }

    #[Test]
    public function it_fails_when_default_app_is_missing(): void
    {
        $this->artisan('flickr:doctor')
            ->expectsOutputToContain('Default Flickr app [default]: missing')
            ->assertFailed();
    }

    #[Test]
    public function it_passes_when_dependencies_are_healthy(): void
    {
        $this->storeApp();
        $this->artisan('flickr:install-indexes')->assertSuccessful();

        $this->artisan('flickr:doctor')
            ->expectsOutputToContain('Default Flickr app [default]: OK')
            ->expectsOutputToContain('Redis PING: OK')
            ->expectsOutputToContain('MongoDB ping: OK')
            ->expectsOutputToContain('Index flickr_photo_groups: OK')
            ->assertSuccessful();
    }

    #[Test]
    public function it_reports_missing_indexes(): void
    {
        $this->storeApp();
        $connection = DB::connection('mongodb');
        foreach (['flickr_apps', 'flickr_tokens', 'flickr_contacts', 'flickr_photos', 'flickr_photo_groups', 'flickr_photo_favorites'] as $collection) {
            try {
                $connection->getCollection($collection)->drop();
            } catch (\Throwable) {
            }
        }
        $this->storeApp();

        $this->artisan('flickr:doctor')
            ->expectsOutputToContain('Default Flickr app [default]: OK')
            ->expectsOutputToContain('Index flickr_apps: missing')
            ->assertFailed();
    }

    #[Test]
    public function it_warns_when_redis_is_unavailable(): void
    {
        $this->storeApp();
        Redis::shouldReceive('connection')->andThrow(new RuntimeException('redis down'));

        $this->artisan('flickr:doctor')
            ->expectsOutputToContain('Redis PING: unavailable')
            ->assertFailed();
    }

    #[Test]
    public function it_handles_non_mongo_connection(): void
    {
        $this->storeApp();
        $nonMongo = $this->createStub(Connection::class);
        DB::shouldReceive('connection')->with('mongodb')->andReturn($nonMongo);

        $this->artisan('flickr:doctor')
            ->expectsOutputToContain('mongodb connection is not a MongoDB Laravel connection')
            ->assertFailed();
    }

    #[Test]
    public function it_warns_when_queue_redis_and_mongo_throw(): void
    {
        $this->storeApp();
        Queue::shouldReceive('connection')->andThrow(new RuntimeException('queue down'));
        Redis::shouldReceive('connection')->andThrow(new RuntimeException('redis down'));
        DB::shouldReceive('connection')->with('mongodb')->andThrow(new RuntimeException('mongo down'));

        $this->artisan('flickr:doctor')
            ->expectsOutputToContain('Redis PING: unavailable')
            ->expectsOutputToContain('MongoDB ping: unavailable')
            ->expectsOutputToContain('Queue connection: unavailable')
            ->assertFailed();
    }

    #[Test]
    public function index_exists_skips_non_object_index_entries(): void
    {
        $command = app(FlickrDoctorCommand::class);
        $method = (new ReflectionClass($command))->getMethod('indexExists');
        $method->setAccessible(true);

        $collection = $this->createStub(Collection::class);
        $collection->method('listIndexes')->willReturn(new \ArrayIterator([
            'not-an-object',
            new IndexWithoutGetKey(),
        ]));
        $connection = $this->createStub(\MongoDB\Laravel\Connection::class);
        $connection->method('getCollection')->willReturn($collection);

        $this->assertFalse($method->invoke($command, $connection, 'flickr_apps', ['name']));
    }

    #[Test]
    public function it_skips_index_check_when_list_indexes_throws(): void
    {
        $this->storeApp();

        $collection = $this->createStub(Collection::class);
        $collection->method('listIndexes')->willThrowException(new RuntimeException('listIndexes failed'));
        $mongo = $this->createStub(\MongoDB\Laravel\Connection::class);
        $mongo->method('getMongoClient')->willReturn(new FakeMongoClientForDoctor());
        $mongo->method('getCollection')->willReturn($collection);
        DB::shouldReceive('connection')->with('mongodb')->andReturn($mongo);

        $this->artisan('flickr:doctor')
            ->expectsOutputToContain('Index check skipped')
            ->assertFailed();
    }
}
