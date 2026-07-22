<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\OAuth;

final readonly class PendingAuthorization
{
    public function __construct(
        public string $oauthTokenSecret,
        public string $appName,
        public ?string $correlationId,
    ) {}
}
