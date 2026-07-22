<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Events;

use Carbon\CarbonInterface;

final class FlickrContactRemoved
{
    public function __construct(
        public readonly string $ownerNsid,
        public readonly string $contactNsid,
        public readonly CarbonInterface $lastSeenAt,
    ) {}
}
