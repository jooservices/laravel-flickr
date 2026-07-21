<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr\Support;

use Jooservices\LaravelFlickr\Dto\PageRequest;

/**
 * Product-neutral default query parameters for page fetch helpers.
 */
final class QueryDefaults
{
    public function defaultPageRequest(?PageRequest $page = null): PageRequest
    {
        if ($page !== null) {
            return $page;
        }

        $configured = config('laravel-flickr.default_per_page', 100);
        $perPage = is_numeric($configured) ? (int) $configured : 100;

        return new PageRequest(1, max(1, min(500, $perPage)));
    }

    /**
     * @return array<string, int|string>
     */
    public function peoplePhotosFilters(): array
    {
        /** @var array<string, mixed> $cfg */
        $cfg = (array) config('laravel-flickr.people_photos', []);

        return [
            'extras' => is_string($cfg['extras'] ?? null) ? $cfg['extras'] : '',
            'safe_search' => is_numeric($cfg['safe_search'] ?? null) ? (int) $cfg['safe_search'] : 3,
            'content_type' => is_numeric($cfg['content_type'] ?? null) ? (int) $cfg['content_type'] : 7,
            'privacy_filter' => is_numeric($cfg['privacy_filter'] ?? null) ? (int) $cfg['privacy_filter'] : 1,
        ];
    }
}
