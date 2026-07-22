<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Events;

final class FlickrClientResolved
{
    public function __construct(
        public readonly string $appName,
        public readonly ?string $nsid,
        public readonly bool $authenticated,
    ) {}
}
