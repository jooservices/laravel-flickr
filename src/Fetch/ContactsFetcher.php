<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr\Fetch;

use JOOservices\Flickr\Flickr;
use Jooservices\LaravelFlickr\Dto\PagedResult;
use Jooservices\LaravelFlickr\Dto\PageRequest;

/**
 * Sync page helpers for flickr.contacts.* — no multi-page loops.
 */
final class ContactsFetcher extends AbstractPageFetcher
{
    public function listPage(
        Flickr $client,
        PageRequest|int $page = 1,
        ?int $perPage = null,
    ): PagedResult {
        $request = $this->page($page, $perPage);
        $response = $this->call($client, 'flickr.contacts.getList', [
            'page' => $request->page,
            'per_page' => $request->perPage,
        ]);

        return $this->toPaged($response, 'contacts', ['contact']);
    }

    public function publicListPage(
        Flickr $client,
        string $userId,
        PageRequest|int $page = 1,
        ?int $perPage = null,
        bool $authenticated = false,
    ): PagedResult {
        $request = $this->page($page, $perPage);
        $response = $this->call($client, 'flickr.contacts.getPublicList', [
            'user_id' => $userId,
            'page' => $request->page,
            'per_page' => $request->perPage,
        ], authenticated: $authenticated);

        return $this->toPaged($response, 'contacts', ['contact']);
    }
}
