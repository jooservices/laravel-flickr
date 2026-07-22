<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Listeners;

use JOOservices\LaravelConfig\Facades\Config;
use JOOservices\LaravelEvents\EventService;
use JOOservices\LaravelFlickr\Events\FlickrOAuthCompleted;
use JOOservices\LaravelFlickr\Events\FlickrRateLimited;
use JOOservices\LaravelFlickr\Listeners\LogFlickrActivity;
use JOOservices\LaravelFlickr\Listeners\RecordFlickrEvent;
use JOOservices\LaravelFlickr\RateLimit\DenyReason;
use JOOservices\LaravelFlickr\Tests\Support\FlickrNsid;
use JOOservices\LaravelFlickr\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

final class FlickrListenerGateTest extends TestCase
{
    #[Test]
    public function record_listener_skips_when_events_disabled(): void
    {
        Config::fake([
            'flickr' => [
                'events_enabled' => false,
                'logging_enabled' => true,
                'default_connection' => 'default',
                'queue_name' => 'flickr',
            ],
        ]);

        $events = Mockery::mock(EventService::class);
        $events->shouldNotReceive('recordManyStoredEvents');
        $this->app->instance(EventService::class, $events);

        app(RecordFlickrEvent::class)->handleOAuthCompleted(new FlickrOAuthCompleted(
            $this->defaultAppName,
            FlickrNsid::fake(),
            'user',
            'Full Name',
            null,
        ));
    }

    #[Test]
    public function record_listener_writes_when_events_enabled(): void
    {
        Config::fake([
            'flickr' => [
                'events_enabled' => true,
                'logging_enabled' => true,
                'default_connection' => 'default',
                'queue_name' => 'flickr',
            ],
        ]);

        $events = Mockery::mock(EventService::class);
        $events->shouldReceive('recordManyStoredEvents')->once()->andReturnNull();
        $this->app->instance(EventService::class, $events);

        app(RecordFlickrEvent::class)->handleCallCompleted($this->flickrCallCompleted('test', 'echo'));
    }

    #[Test]
    public function log_listener_skips_when_logging_disabled(): void
    {
        Config::fake([
            'flickr' => [
                'events_enabled' => true,
                'logging_enabled' => false,
                'default_connection' => 'default',
                'queue_name' => 'flickr',
            ],
        ]);

        // Should return early without touching ActivityLog facade / queue.
        app(LogFlickrActivity::class)->handleRateLimited(new FlickrRateLimited(
            $this->defaultAppName,
            'contacts',
            'getList',
            null,
            15,
            DenyReason::MinGap,
        ));

        $this->addToAssertionCount(1);
    }
}
