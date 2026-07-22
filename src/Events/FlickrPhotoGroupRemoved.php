<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Events;

use Carbon\CarbonInterface;

final class FlickrPhotoGroupRemoved
{
    public function __construct(
        public readonly string $ownerNsid,
        public readonly string $photoId,
        public readonly string $groupType,
        public readonly string $groupId,
        public readonly CarbonInterface $lastSeenAt,
    ) {}
}
