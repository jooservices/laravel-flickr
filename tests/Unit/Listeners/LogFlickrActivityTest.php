<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Listeners;

use Carbon\Carbon;
use JOOservices\LaravelConfig\Facades\Config;
use JOOservices\LaravelFlickr\Events\FlickrCallFailed;
use JOOservices\LaravelFlickr\Events\FlickrClientResolved;
use JOOservices\LaravelFlickr\Events\FlickrContactRemoved;
use JOOservices\LaravelFlickr\Events\FlickrOAuthCompleted;
use JOOservices\LaravelFlickr\Events\FlickrOAuthRevoked;
use JOOservices\LaravelFlickr\Events\FlickrPhotoGroupRemoved;
use JOOservices\LaravelFlickr\Events\FlickrPhotoRemoved;
use JOOservices\LaravelFlickr\Events\FlickrPhotoUnfavorited;
use JOOservices\LaravelFlickr\Events\FlickrRateLimitApproaching;
use JOOservices\LaravelFlickr\Events\FlickrRateLimited;
use JOOservices\LaravelFlickr\Listeners\LogFlickrActivity;
use JOOservices\LaravelFlickr\RateLimit\DenyReason;
use JOOservices\LaravelFlickr\Tests\Support\FlickrNsid;
use JOOservices\LaravelFlickr\Tests\TestCase;
use JOOservices\LaravelLogging\ActivityLogManager;
use JOOservices\LaravelLogging\Contracts\LogAdapterInterface;
use JOOservices\LaravelLogging\Facades\ActivityLog;
use JOOservices\LaravelLogging\Models\ActivityLogRecord;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

final class LogFlickrActivityTest extends TestCase
{
    private LogAdapterInterface $adapter;

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

        $this->adapter = Mockery::mock(LogAdapterInterface::class);
        $this->adapter->shouldReceive('action')->andReturnSelf()->byDefault();
        $this->adapter->shouldReceive('by')->andReturnSelf()->byDefault();
        $this->adapter->shouldReceive('properties')->andReturnSelf()->byDefault();
        $this->adapter->shouldReceive('queue')->andReturnSelf()->byDefault();
        $this->adapter->shouldReceive('dispatch')->byDefault();
        $this->adapter->shouldReceive('save')
            ->andReturn(Mockery::mock(ActivityLogRecord::class))
            ->byDefault();

        $manager = Mockery::mock(ActivityLogManager::class);
        $manager->shouldReceive('activity')->andReturn($this->adapter)->byDefault();
        $manager->shouldReceive('system')->andReturn($this->adapter)->byDefault();
        $manager->shouldReceive('security')->andReturn($this->adapter)->byDefault();
        ActivityLog::swap($manager);
    }

    #[Test]
    public function it_logs_call_completed_via_queued_activity_adapter(): void
    {
        $this->adapter->shouldReceive('action')->once()->with('flickr.test.echo')->andReturnSelf();
        $this->adapter->shouldReceive('by')->once()->with('anonymous')->andReturnSelf();
        $this->adapter->shouldReceive('properties')->once()->andReturnSelf();
        $this->adapter->shouldReceive('queue')->once()->with('flickr')->andReturnSelf();
        $this->adapter->shouldReceive('dispatch')->once();

        app(LogFlickrActivity::class)->handleCallCompleted($this->flickrCallCompleted('test', 'echo'));
    }

    #[Test]
    public function it_logs_call_failed_via_system_adapter(): void
    {
        $this->adapter->shouldReceive('action')->once()->with('flickr.photos.getInfo.failed')->andReturnSelf();
        $this->adapter->shouldReceive('properties')->once()->andReturnSelf();
        $this->adapter->shouldReceive('save')->once();

        app(LogFlickrActivity::class)->handleCallFailed(new FlickrCallFailed(
            'photos',
            'getInfo',
            $this->defaultAppName,
            FlickrNsid::fake(),
            [],
            false,
            'RuntimeException',
            'boom',
        ));
    }

    #[Test]
    public function it_logs_rate_limit_events(): void
    {
        $this->adapter->shouldReceive('action')->once()->with('flickr.rate_limited')->andReturnSelf();
        $this->adapter->shouldReceive('save')->once();
        app(LogFlickrActivity::class)->handleRateLimited(new FlickrRateLimited(
            $this->defaultAppName,
            'contacts',
            'getList',
            null,
            15,
            DenyReason::MinGap,
        ));

        $this->adapter->shouldReceive('action')->once()->with('flickr.rate_limit.approaching')->andReturnSelf();
        $this->adapter->shouldReceive('save')->once();
        app(LogFlickrActivity::class)->handleRateLimitApproaching(new FlickrRateLimitApproaching(
            'default',
            100,
            3300,
            96.9,
        ));
    }

    #[Test]
    public function it_logs_oauth_and_client_lifecycle_events(): void
    {
        $nsid = FlickrNsid::fake();

        $this->adapter->shouldReceive('action')->once()->with('flickr.oauth.completed')->andReturnSelf();
        $this->adapter->shouldReceive('by')->once()->with($nsid)->andReturnSelf();
        $this->adapter->shouldReceive('save')->once();
        app(LogFlickrActivity::class)->handleOAuthCompleted(new FlickrOAuthCompleted(
            $this->defaultAppName,
            $nsid,
            'user',
            'Full Name',
            'corr-1',
        ));

        $this->adapter->shouldReceive('action')->once()->with('flickr.oauth.revoked')->andReturnSelf();
        $this->adapter->shouldReceive('by')->once()->with($nsid)->andReturnSelf();
        $this->adapter->shouldReceive('save')->once();
        app(LogFlickrActivity::class)->handleOAuthRevoked(new FlickrOAuthRevoked($this->defaultAppName, $nsid));

        $this->adapter->shouldReceive('action')->once()->with('flickr.client.resolved')->andReturnSelf();
        $this->adapter->shouldReceive('by')->once()->with($nsid)->andReturnSelf();
        $this->adapter->shouldReceive('save')->once();
        app(LogFlickrActivity::class)->handleClientResolved(new FlickrClientResolved($this->defaultAppName, $nsid, true));
    }

    #[Test]
    public function it_logs_persistence_removal_events(): void
    {
        $owner = FlickrNsid::fake();
        $seenAt = Carbon::parse('2026-01-15T12:00:00Z');

        $this->adapter->shouldReceive('action')->once()->with('flickr.contact.removed')->andReturnSelf();
        $this->adapter->shouldReceive('save')->once();
        app(LogFlickrActivity::class)->handleContactRemoved(new FlickrContactRemoved(
            $owner,
            FlickrNsid::fake(),
            $seenAt,
        ));

        $this->adapter->shouldReceive('action')->once()->with('flickr.photo.removed')->andReturnSelf();
        $this->adapter->shouldReceive('save')->once();
        app(LogFlickrActivity::class)->handlePhotoRemoved(new FlickrPhotoRemoved($owner, 'photo-1', $seenAt));

        $this->adapter->shouldReceive('action')->once()->with('flickr.photo.group_removed')->andReturnSelf();
        $this->adapter->shouldReceive('save')->once();
        app(LogFlickrActivity::class)->handlePhotoGroupRemoved(new FlickrPhotoGroupRemoved(
            $owner,
            'photo-1',
            'photoset',
            'set-1',
            $seenAt,
        ));

        $this->adapter->shouldReceive('action')->once()->with('flickr.photo.unfavorited')->andReturnSelf();
        $this->adapter->shouldReceive('save')->once();
        app(LogFlickrActivity::class)->handlePhotoUnfavorited(new FlickrPhotoUnfavorited(
            $owner,
            'photo-1',
            $seenAt,
        ));
    }

    #[Test]
    public function it_skips_all_handlers_when_logging_is_disabled(): void
    {
        Config::fake([
            'flickr' => [
                'events_enabled' => true,
                'logging_enabled' => false,
                'default_connection' => 'default',
                'queue_name' => 'flickr',
            ],
        ]);

        $this->adapter->shouldReceive('action')->never();
        $this->adapter->shouldReceive('dispatch')->never();
        $this->adapter->shouldReceive('save')->never();

        $listener = app(LogFlickrActivity::class);
        $owner = FlickrNsid::fake();
        $seenAt = Carbon::now();

        $listener->handleCallCompleted($this->flickrCallCompleted('test', 'echo'));
        $listener->handleCallFailed(new FlickrCallFailed(
            'photos',
            'getInfo',
            $this->defaultAppName,
            $owner,
            [],
            false,
            'RuntimeException',
            'boom',
        ));
        $listener->handleRateLimited(new FlickrRateLimited($this->defaultAppName, 'contacts', 'getList', null, 15, DenyReason::MinGap));
        $listener->handleRateLimitApproaching(new FlickrRateLimitApproaching('default', 100, 3300, 96.9));
        $listener->handleOAuthCompleted(new FlickrOAuthCompleted($this->defaultAppName, $owner, 'user', 'Full Name', null));
        $listener->handleOAuthRevoked(new FlickrOAuthRevoked($this->defaultAppName, $owner));
        $listener->handleClientResolved(new FlickrClientResolved($this->defaultAppName, $owner, true));
        $listener->handleContactRemoved(new FlickrContactRemoved($owner, FlickrNsid::fake(), $seenAt));
        $listener->handlePhotoRemoved(new FlickrPhotoRemoved($owner, 'photo-1', $seenAt));
        $listener->handlePhotoGroupRemoved(new FlickrPhotoGroupRemoved($owner, 'photo-1', 'photoset', 'set-1', $seenAt));
        $listener->handlePhotoUnfavorited(new FlickrPhotoUnfavorited($owner, 'photo-1', $seenAt));

        $this->addToAssertionCount(1);
    }
}
