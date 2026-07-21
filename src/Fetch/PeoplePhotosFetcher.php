<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr\Fetch;

use JOOservices\Flickr\Flickr;
use Jooservices\LaravelFlickr\Dto\PagedResult;
use Jooservices\LaravelFlickr\Dto\PageRequest;

/**
 * Sync page helpers for people photo lists.
 */
final class PeoplePhotosFetcher extends AbstractPageFetcher
{
    /**
     * @param  array<string, mixed>  $extraParams  Merged after defaults (caller wins).
     */
    public function listPage(
        Flickr $client,
        string $userId,
        PageRequest|int $page = 1,
        ?int $perPage = null,
        array $extraParams = [],
        bool $authenticated = true,
    ): PagedResult {
        $request = $this->page($page, $perPage);
        $filters = $this->defaults->peoplePhotosFilters();

        $params = array_merge($filters, $extraParams, [
            'user_id' => $userId,
            'page' => $request->page,
            'per_page' => $request->perPage,
        ]);

        $response = $this->call($client, 'flickr.people.getPhotos', $params, $authenticated);

        return $this->toPaged($response, 'photos', ['photo']);
    }

    /**
     * @param  array<string, mixed>  $extraParams
     */
    public function publicListPage(
        Flickr $client,
        string $userId,
        PageRequest|int $page = 1,
        ?int $perPage = null,
        array $extraParams = [],
    ): PagedResult {
        $request = $this->page($page, $perPage);
        $params = array_merge($extraParams, [
            'user_id' => $userId,
            'page' => $request->page,
            'per_page' => $request->perPage,
        ]);

        $response = $this->call($client, 'flickr.people.getPublicPhotos', $params, authenticated: false);

        return $this->toPaged($response, 'photos', ['photo']);
    }
}
