<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Listeners;

use Carbon\Carbon;
use JOOservices\LaravelConfig\Facades\Config;
use JOOservices\LaravelEvents\Data\StoredEventData;
use JOOservices\LaravelEvents\EventService;
use JOOservices\LaravelFlickr\Events\FlickrContactRemoved;
use JOOservices\LaravelFlickr\Events\FlickrOAuthCompleted;
use JOOservices\LaravelFlickr\Events\FlickrOAuthRevoked;
use JOOservices\LaravelFlickr\Events\FlickrPhotoGroupRemoved;
use JOOservices\LaravelFlickr\Events\FlickrPhotoRemoved;
use JOOservices\LaravelFlickr\Events\FlickrPhotoUnfavorited;
use JOOservices\LaravelFlickr\Listeners\RecordFlickrEvent;
use JOOservices\LaravelFlickr\Tests\Support\FlickrNsid;
use JOOservices\LaravelFlickr\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

final class RecordFlickrEventTest extends TestCase
{
    /** @var list<string> */
    private array $recordedActions = [];

    protected function setUp(): void
    {
        parent::setUp();

        Config::fake([
            'flickr' => [
                'events_enabled' => true,
                'logging_enabled' => true,
                'default_connection' => 'default',
                'queue_name' => 'flickr',
            ],
        ]);

        $this->recordedActions = [];
        $events = Mockery::mock(EventService::class);
        $events->shouldReceive('recordManyStoredEvents')
            ->andReturnUsing(function (array $batch): void {
                foreach ($batch as $event) {
                    $this->assertInstanceOf(StoredEventData::class, $event);
                    $this->recordedActions[] = $event->eventClass;
                }
            });
        $this->app->instance(EventService::class, $events);
    }

    #[Test]
    public function it_records_all_supported_events_when_enabled(): void
    {
        $listener = app(RecordFlickrEvent::class);
        $owner = FlickrNsid::fake();
        $seenAt = Carbon::now();

        $listener->handleCallCompleted($this->flickrCallCompleted('test', 'echo'));
        $listener->handleOAuthCompleted(new FlickrOAuthCompleted($this->defaultAppName, $owner, 'user', 'Full Name', null));
        $listener->handleOAuthRevoked(new FlickrOAuthRevoked($this->defaultAppName, $owner));
        $listener->handleContactRemoved(new FlickrContactRemoved($owner, FlickrNsid::fake(), $seenAt));
        $listener->handlePhotoRemoved(new FlickrPhotoRemoved($owner, 'photo-1', $seenAt));
        $listener->handlePhotoGroupRemoved(new FlickrPhotoGroupRemoved(
            $owner,
            'photo-1',
            'photoset',
            'set-1',
            $seenAt,
        ));
        $listener->handlePhotoUnfavorited(new FlickrPhotoUnfavorited($owner, 'photo-1', $seenAt));

        $this->assertSame([
            'flickr.test.echo',
            'flickr.oauth.completed',
            'flickr.oauth.revoked',
            'flickr.contact.removed',
            'flickr.photo.removed',
            'flickr.photo.group_removed',
            'flickr.photo.unfavorited',
        ], $this->recordedActions);
    }

    #[Test]
    public function it_skips_all_handlers_when_events_are_disabled(): void
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

        $listener = app(RecordFlickrEvent::class);
        $owner = FlickrNsid::fake();
        $seenAt = Carbon::now();

        $listener->handleCallCompleted($this->flickrCallCompleted('test', 'echo'));
        $listener->handleOAuthCompleted(new FlickrOAuthCompleted($this->defaultAppName, $owner, 'user', 'Full Name', null));
        $listener->handleOAuthRevoked(new FlickrOAuthRevoked($this->defaultAppName, $owner));
        $listener->handleContactRemoved(new FlickrContactRemoved($owner, FlickrNsid::fake(), $seenAt));
        $listener->handlePhotoRemoved(new FlickrPhotoRemoved($owner, 'photo-1', $seenAt));
        $listener->handlePhotoGroupRemoved(new FlickrPhotoGroupRemoved($owner, 'photo-1', 'photoset', 'set-1', $seenAt));
        $listener->handlePhotoUnfavorited(new FlickrPhotoUnfavorited($owner, 'photo-1', $seenAt));
    }
}
