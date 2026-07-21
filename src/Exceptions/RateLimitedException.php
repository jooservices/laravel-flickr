<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr\Exceptions;

use RuntimeException;

final class RateLimitedException extends RuntimeException
{
    public function __construct(
        public readonly int $retryAfterSeconds,
        string $message = 'Flickr request rate limited.',
    ) {
        parent::__construct($message);
    }
}
