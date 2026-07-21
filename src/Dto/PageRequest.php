<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr\Dto;

use InvalidArgumentException;

/**
 * 1-based page request for Flickr list endpoints.
 */
final readonly class PageRequest
{
    public function __construct(
        public int $page = 1,
        public int $perPage = 100,
    ) {
        if ($this->page < 1) {
            throw new InvalidArgumentException('Page must be >= 1.');
        }

        if ($this->perPage < 1 || $this->perPage > 500) {
            throw new InvalidArgumentException('Per page must be between 1 and 500.');
        }
    }
}
