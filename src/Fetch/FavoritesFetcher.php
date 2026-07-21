<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr\Fetch;

use JOOservices\Flickr\Flickr;
use Jooservices\LaravelFlickr\Dto\PagedResult;
use Jooservices\LaravelFlickr\Dto\PageRequest;

final class FavoritesFetcher extends AbstractPageFetcher
{
    /**
     * @param  array<string, mixed>  $extraParams
     */
    public function listPage(
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

        $response = $this->call($client, 'flickr.favorites.getList', $params);

        return $this->toPaged($response, 'photos', ['photo']);
    }
}
