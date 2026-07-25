<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Service;

use Carbon\CarbonInterface;
use JOOservices\LaravelFlickr\Events\FlickrContactRemoved;
use JOOservices\LaravelFlickr\Events\FlickrPhotoGroupRemoved;
use JOOservices\LaravelFlickr\Events\FlickrPhotoRemoved;
use JOOservices\LaravelFlickr\Events\FlickrPhotoUnfavorited;
use JOOservices\LaravelFlickr\Repositories\ContactRepository;
use JOOservices\LaravelFlickr\Repositories\PhotoFavoriteRepository;
use JOOservices\LaravelFlickr\Repositories\PhotoGroupRepository;
use JOOservices\LaravelFlickr\Repositories\PhotoRepository;

/**
 * Soft-removes stale persistence rows and emits domain events.
 * Repositories only mutate data; this service owns side effects.
 */
final class PersistenceReconcileService
{
    public function __construct(
        private readonly ContactRepository $contacts,
        private readonly PhotoRepository $photos,
        private readonly PhotoGroupRepository $photoGroups,
        private readonly PhotoFavoriteRepository $photoFavorites,
    ) {}

    public function reconcileContacts(string $ownerNsid, CarbonInterface $completedBefore): int
    {
        $removed = $this->contacts->markStaleRemoved($ownerNsid, $completedBefore);
        foreach ($removed as $row) {
            event(new FlickrContactRemoved($ownerNsid, $row['contact_nsid'], $row['last_seen_at']));
        }

        return count($removed);
    }

    public function reconcilePhotos(string $ownerNsid, CarbonInterface $completedBefore): int
    {
        $removed = $this->photos->markStaleRemoved($ownerNsid, $completedBefore);
        foreach ($removed as $row) {
            event(new FlickrPhotoRemoved($ownerNsid, $row['photo_id'], $row['last_seen_at']));
        }

        return count($removed);
    }

    public function reconcilePhotoGroup(
        string $ownerNsid,
        string $groupType,
        string $groupId,
        CarbonInterface $completedBefore,
    ): int {
        $removed = $this->photoGroups->markStaleRemoved($ownerNsid, $groupType, $groupId, $completedBefore);
        foreach ($removed as $row) {
            event(new FlickrPhotoGroupRemoved(
                $ownerNsid,
                $row['photo_id'],
                $groupType,
                $groupId,
                $row['last_seen_at'],
            ));
        }

        return count($removed);
    }

    public function reconcileFavorites(string $ownerNsid, CarbonInterface $completedBefore): int
    {
        $removed = $this->photoFavorites->markStaleRemoved($ownerNsid, $completedBefore);
        foreach ($removed as $row) {
            event(new FlickrPhotoUnfavorited($ownerNsid, $row['photo_id'], $row['last_seen_at']));
        }

        return count($removed);
    }
}
