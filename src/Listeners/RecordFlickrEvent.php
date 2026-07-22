<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Listeners;

use JOOservices\LaravelEvents\Data\StoredEventData;
use JOOservices\LaravelEvents\EventService;
use JOOservices\LaravelFlickr\Contracts\RuntimeSettingsResolverInterface;
use JOOservices\LaravelFlickr\Events\FlickrCallCompleted;
use JOOservices\LaravelFlickr\Events\FlickrContactRemoved;
use JOOservices\LaravelFlickr\Events\FlickrOAuthCompleted;
use JOOservices\LaravelFlickr\Events\FlickrOAuthRevoked;
use JOOservices\LaravelFlickr\Events\FlickrPhotoGroupRemoved;
use JOOservices\LaravelFlickr\Events\FlickrPhotoRemoved;
use JOOservices\LaravelFlickr\Events\FlickrPhotoUnfavorited;

final class RecordFlickrEvent
{
    public function __construct(
        private readonly EventService $events,
        private readonly RuntimeSettingsResolverInterface $runtimeSettings,
    ) {}

    public function handleCallCompleted(FlickrCallCompleted $event): void
    {
        if (! $this->runtimeSettings->eventsEnabled()) {
            return;
        }

        $this->events->recordManyStoredEvents([
            new StoredEventData(
                "flickr.{$event->namespace}.{$event->method}",
                [
                    'app_name' => $event->appName,
                    'items' => $event->outcome->itemCount,
                    'ok' => $event->outcome->ok,
                    'duration_ms' => $event->outcome->durationMs,
                ],
                $event->nsid ?? 'anonymous',
            ),
        ]);
    }

    public function handleOAuthCompleted(FlickrOAuthCompleted $event): void
    {
        if (! $this->runtimeSettings->eventsEnabled()) {
            return;
        }

        $this->events->recordManyStoredEvents([
            new StoredEventData(
                'flickr.oauth.completed',
                [
                    'app_name' => $event->appName,
                    'username' => $event->username,
                    'correlation_id' => $event->correlationId,
                ],
                $event->nsid,
            ),
        ]);
    }

    public function handleOAuthRevoked(FlickrOAuthRevoked $event): void
    {
        if (! $this->runtimeSettings->eventsEnabled()) {
            return;
        }

        $this->events->recordManyStoredEvents([
            new StoredEventData('flickr.oauth.revoked', ['app_name' => $event->appName], $event->nsid),
        ]);
    }

    public function handleContactRemoved(FlickrContactRemoved $event): void
    {
        if (! $this->runtimeSettings->eventsEnabled()) {
            return;
        }

        $this->events->recordManyStoredEvents([
            new StoredEventData('flickr.contact.removed', ['contact_nsid' => $event->contactNsid], $event->ownerNsid),
        ]);
    }

    public function handlePhotoRemoved(FlickrPhotoRemoved $event): void
    {
        if (! $this->runtimeSettings->eventsEnabled()) {
            return;
        }

        $this->events->recordManyStoredEvents([
            new StoredEventData('flickr.photo.removed', ['photo_id' => $event->photoId], $event->ownerNsid),
        ]);
    }

    public function handlePhotoGroupRemoved(FlickrPhotoGroupRemoved $event): void
    {
        if (! $this->runtimeSettings->eventsEnabled()) {
            return;
        }

        $this->events->recordManyStoredEvents([
            new StoredEventData(
                'flickr.photo.group_removed',
                [
                    'photo_id' => $event->photoId,
                    'group_type' => $event->groupType,
                    'group_id' => $event->groupId,
                ],
                $event->ownerNsid,
            ),
        ]);
    }

    public function handlePhotoUnfavorited(FlickrPhotoUnfavorited $event): void
    {
        if (! $this->runtimeSettings->eventsEnabled()) {
            return;
        }

        $this->events->recordManyStoredEvents([
            new StoredEventData('flickr.photo.unfavorited', ['photo_id' => $event->photoId], $event->ownerNsid),
        ]);
    }
}
