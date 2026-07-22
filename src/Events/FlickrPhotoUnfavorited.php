<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Events;

use Carbon\CarbonInterface;

final class FlickrPhotoUnfavorited
{
    public function __construct(
        public readonly string $ownerNsid,
        public readonly string $photoId,
        public readonly CarbonInterface $lastSeenAt,
    ) {}
}
