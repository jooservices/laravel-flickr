<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Events;

final class FlickrOAuthRevoked
{
    public function __construct(
        public readonly string $appName,
        public readonly string $nsid,
    ) {}
}
