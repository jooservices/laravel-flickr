<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr\Dto;

final readonly class OAuthBeginResult
{
    public function __construct(
        public string $authorizationUrl,
        public string $requestToken,
        public string $requestTokenSecret,
    ) {}
}
