<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Dto;

/**
 * Named Flickr API application (connection) stored in MongoDB.
 */
final readonly class FlickrApp
{
    public function __construct(
        public string $name,
        public string $apiKey,
        public string $apiSecret,
    ) {}

    public function credentials(): AppCredentials
    {
        return new AppCredentials($this->apiKey, $this->apiSecret);
    }
}
