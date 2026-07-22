<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Support;

use Illuminate\Support\Facades\Event;
use JOOservices\Client\Client\ClientBuilder;
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
}
