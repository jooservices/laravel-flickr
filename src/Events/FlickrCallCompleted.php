<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Events;

use JOOservices\LaravelFlickr\Dto\FlickrCallOutcome;

final class FlickrCallCompleted
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function __construct(
        public readonly string $namespace,
        public readonly string $method,
        public readonly string $appName,
        public readonly ?string $nsid,
        public readonly array $params,
        public readonly bool $queued,
        public readonly FlickrCallOutcome $outcome,
    ) {}
}
