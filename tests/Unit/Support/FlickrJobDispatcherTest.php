<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Support;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use JOOservices\Client\Client\ClientBuilder;
use JOOservices\LaravelConfig\Facades\Config;
use JOOservices\LaravelFlickr\Jobs\FlickrRequestJob;
use JOOservices\LaravelFlickr\Jobs\UniqueFlickrRequestJob;
use JOOservices\LaravelFlickr\Support\FlickrJobDispatcher;
use JOOservices\LaravelFlickr\Tests\Support\InteractsWithFlickrAdapters;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class FlickrJobDispatcherTest extends TestCase
{
    use InteractsWithFlickrAdapters;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresMongoDb();
        $this->clearFlickrCollections();
        $this->storeApp();
    }

    #[Test]
    public function applies_default_per_page_only_when_requested(): void
    {
        Event::fake();
        $this->fakeFlickrResponses([
            ['method' => ['_content' => 'flickr.test.echo']],
            ['method' => ['_content' => 'flickr.test.echo']],
        ]);

        FlickrJobDispatcher::dispatch(
            'test',
            'echo',
            $this->defaultAppName,
            null,
            [],
            queued: false,
            bypassCache: true,
            applyDefaultPerPage: true,
        );
        $this->assertFlickrMethodCalled('flickr.test.echo', ['per_page' => '50']);

        $this->fakeFlickrResponses([
            ['method' => ['_content' => 'flickr.test.echo']],
        ]);
        FlickrJobDispatcher::dispatch(
            'test',
            'echo',
            $this->defaultAppName,
            null,
            [],
            queued: false,
            bypassCache: true,
            applyDefaultPerPage: false,
        );

        $calls = 0;
        foreach (ClientBuilder::recorded() as $request) {
            $params = $this->requestParams($request);
            if (($params['method'] ?? null) === 'flickr.test.echo' && ! array_key_exists('per_page', $params)) {
                $calls++;
            }
        }
        $this->assertGreaterThanOrEqual(1, $calls);
    }

    #[Test]
    public function queued_unique_dispatch_uses_unique_job_class(): void
    {
        Bus::fake();

        $result = FlickrJobDispatcher::dispatch(
            'test',
            'echo',
            $this->defaultAppName,
            null,
            ['x' => 1],
            queued: true,
            bypassCache: false,
            unique: true,
            correlationId: 'c-1',
        );

        $this->assertNull($result);
        Bus::assertDispatched(UniqueFlickrRequestJob::class, static function (UniqueFlickrRequestJob $job): bool {
            return $job->correlationId === 'c-1' && $job->params === ['x' => 1];
        });
    }

    #[Test]
    public function applies_queue_connection_and_name_from_runtime_settings(): void
    {
        Bus::fake();
        Config::fake([
            'flickr' => [
                'default_connection' => 'default',
                'queue_connection' => 'sync',
                'queue_name' => 'flickr-edge',
                'rate_limit_enabled' => false,
                'logging_enabled' => false,
                'events_enabled' => false,
                'default_per_page' => 50,
                'oauth_pending_key_prefix' => 'edge-oauth',
                'rate_limit_key_prefix' => 'edge-rl',
            ],
        ]);

        FlickrJobDispatcher::dispatch(
            'test',
            'echo',
            $this->defaultAppName,
            null,
            [],
            queued: true,
            bypassCache: false,
        );

        Bus::assertDispatched(FlickrRequestJob::class, static function (FlickrRequestJob $job): bool {
            return $job->connection === 'sync' && $job->queue === 'flickr-edge';
        });
    }
}
