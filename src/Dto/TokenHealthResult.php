<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr\Dto;

/**
 * Result of a single flickr.test.login probe (sync).
 */
final readonly class TokenHealthResult
{
    public function __construct(
        public bool $valid,
        public ?string $userNsid = null,
        public ?int $errorCode = null,
        public ?string $errorMessage = null,
    ) {}
}
