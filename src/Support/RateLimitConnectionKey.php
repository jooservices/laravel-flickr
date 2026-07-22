<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Support;

/**
 * Derives Redis / event identifiers from a Flickr API key without storing the key material.
 */
final class RateLimitConnectionKey
{
    public static function fromApiKey(string $apiKey): string
    {
        return hash('sha256', $apiKey);
    }
}
