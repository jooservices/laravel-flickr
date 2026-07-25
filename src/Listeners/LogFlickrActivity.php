<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Listeners;

use JOOservices\LaravelFlickr\Contracts\RuntimeSettingsResolverInterface;
use JOOservices\LaravelFlickr\Events\FlickrCallCompleted;
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
use JOOservices\LaravelLogging\Facades\ActivityLog;

final class LogFlickrActivity
{
    public function __construct(private readonly RuntimeSettingsResolverInterface $runtimeSettings) {}

    public function handleCallCompleted(FlickrCallCompleted $event): void
    {
        if (! $this->runtimeSettings->loggingEnabled()) {
            return;
        }

        ActivityLog::activity()
            ->action("flickr.{$event->namespace}.{$event->method}")
            ->by($event->nsid ?? 'anonymous')
            ->properties([
                'app_name' => $event->appName,
                'items' => $event->outcome->itemCount,
                'duration_ms' => $event->outcome->durationMs,
                'quota_remaining' => $event->outcome->quotaRemaining,
                'correlation_id' => $event->correlationId,
            ])
            ->queue($this->runtimeSettings->queueName())
            ->dispatch();
    }

    public function handleCallFailed(FlickrCallFailed $event): void
    {
        if (! $this->runtimeSettings->loggingEnabled()) {
            return;
        }

        ActivityLog::system()
            ->action("flickr.{$event->namespace}.{$event->method}.failed")
            ->by($event->nsid ?? 'anonymous')
            ->properties([
                'app_name' => $event->appName,
                'nsid' => $event->nsid,
                'queued' => $event->queued,
                'exception' => $event->exceptionClass,
                'message' => $event->exceptionMessage,
                'correlation_id' => $event->correlationId,
            ])
            ->queue($this->runtimeSettings->queueName())
            ->dispatch();
    }

    public function handleRateLimited(FlickrRateLimited $event): void
    {
        if (! $this->runtimeSettings->loggingEnabled()) {
            return;
        }

        ActivityLog::system()
            ->action('flickr.rate_limited')
            ->properties([
                'app_name' => $event->appName,
                'namespace' => $event->namespace,
                'method' => $event->method,
                'nsid' => $event->nsid,
                'reason' => $event->reason->value,
                'retry_after_seconds' => $event->retryAfterSeconds,
            ])
            ->queue($this->runtimeSettings->queueName())
            ->dispatch();
    }

    public function handleRateLimitApproaching(FlickrRateLimitApproaching $event): void
    {
        if (! $this->runtimeSettings->loggingEnabled()) {
            return;
        }

        ActivityLog::system()
            ->action('flickr.rate_limit.approaching')
            ->properties(['remaining' => $event->remaining, 'limit' => $event->limit, 'percent_used' => $event->percentUsed])
            ->queue($this->runtimeSettings->queueName())
            ->dispatch();
    }

    public function handleOAuthCompleted(FlickrOAuthCompleted $event): void
    {
        if (! $this->runtimeSettings->loggingEnabled()) {
            return;
        }

        ActivityLog::activity()
            ->action('flickr.oauth.completed')
            ->by($event->nsid)
            ->properties([
                'app_name' => $event->appName,
                'username' => $event->username,
                'correlation_id' => $event->correlationId,
            ])
            ->save();
    }

    public function handleOAuthRevoked(FlickrOAuthRevoked $event): void
    {
        if (! $this->runtimeSettings->loggingEnabled()) {
            return;
        }

        ActivityLog::security()
            ->action('flickr.oauth.revoked')
            ->by($event->nsid)
            ->properties(['app_name' => $event->appName])
            ->save();
    }

    public function handleClientResolved(FlickrClientResolved $event): void
    {
        if (! $this->runtimeSettings->loggingEnabled()) {
            return;
        }

        ActivityLog::system()
            ->action('flickr.client.resolved')
            ->by($event->nsid ?? 'anonymous')
            ->properties(['app_name' => $event->appName, 'authenticated' => $event->authenticated])
            ->save();
    }

    public function handleContactRemoved(FlickrContactRemoved $event): void
    {
        if (! $this->runtimeSettings->loggingEnabled()) {
            return;
        }

        ActivityLog::activity()
            ->action('flickr.contact.removed')
            ->by($event->ownerNsid)
            ->properties(['contact_nsid' => $event->contactNsid, 'last_seen_at' => $event->lastSeenAt->toIso8601String()])
            ->save();
    }

    public function handlePhotoRemoved(FlickrPhotoRemoved $event): void
    {
        if (! $this->runtimeSettings->loggingEnabled()) {
            return;
        }

        ActivityLog::activity()
            ->action('flickr.photo.removed')
            ->by($event->ownerNsid)
            ->properties(['photo_id' => $event->photoId, 'last_seen_at' => $event->lastSeenAt->toIso8601String()])
            ->save();
    }

    public function handlePhotoGroupRemoved(FlickrPhotoGroupRemoved $event): void
    {
        if (! $this->runtimeSettings->loggingEnabled()) {
            return;
        }

        ActivityLog::activity()
            ->action('flickr.photo.group_removed')
            ->by($event->ownerNsid)
            ->properties(['photo_id' => $event->photoId, 'group_type' => $event->groupType, 'group_id' => $event->groupId])
            ->save();
    }

    public function handlePhotoUnfavorited(FlickrPhotoUnfavorited $event): void
    {
        if (! $this->runtimeSettings->loggingEnabled()) {
            return;
        }

        ActivityLog::activity()
            ->action('flickr.photo.unfavorited')
            ->by($event->ownerNsid)
            ->properties(['photo_id' => $event->photoId])
            ->save();
    }
}
