<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Dto;

use JOOservices\LaravelFlickr\Exceptions\MissingCredentialsException;

/**
 * Flickr application API key + secret (not a user OAuth token).
 */
final readonly class AppCredentials
{
    public function __construct(
        public string $apiKey,
        public string $apiSecret,
    ) {
        if (trim($this->apiKey) === '' || trim($this->apiSecret) === '') {
            throw MissingCredentialsException::emptyAppCredentials();
        }
    }
}
