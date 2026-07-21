<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr\Dto;

use JOOservices\Flickr\DTO\Common\ApiResponseData;

/**
 * Normalized single-page result from a Flickr list endpoint.
 *
 * Host apps decide multi-page walks, queues, and persistence — this package only returns one page.
 *
 * @phpstan-type Item array<string, mixed>
 */
final readonly class PagedResult
{
    /**
     * @param  list<Item>  $items
     */
    public function __construct(
        public bool $ok,
        public int $page,
        public int $pages,
        public int $perPage,
        public int $total,
        public array $items,
        public ?int $errorCode,
        public ?string $errorMessage,
        public ApiResponseData $raw,
    ) {}

    public function hasMorePages(): bool
    {
        return $this->ok && $this->page < $this->pages;
    }
}
