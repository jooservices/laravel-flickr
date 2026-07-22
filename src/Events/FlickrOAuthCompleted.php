<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Events;

final class FlickrOAuthCompleted
{
    public function __construct(
        public readonly string $appName,
        public readonly string $nsid,
        public readonly ?string $username,
        public readonly ?string $fullname,
        public readonly ?string $correlationId,
    ) {}
}
