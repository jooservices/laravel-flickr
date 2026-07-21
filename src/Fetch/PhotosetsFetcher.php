<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr\Fetch;

use JOOservices\Flickr\Flickr;
use Jooservices\LaravelFlickr\Dto\PagedResult;
use Jooservices\LaravelFlickr\Dto\PageRequest;

final class PhotosetsFetcher extends AbstractPageFetcher
{
    public function listPage(
        Flickr $client,
        string $userId,
        PageRequest|int $page = 1,
        ?int $perPage = null,
    ): PagedResult {
        $request = $this->page($page, $perPage);
        $response = $this->call($client, 'flickr.photosets.getList', [
            'user_id' => $userId,
            'page' => $request->page,
            'per_page' => $request->perPage,
        ]);

        return $this->toPaged($response, 'photosets', ['photoset']);
    }

    public function photosPage(
        Flickr $client,
        string $photosetId,
        PageRequest|int $page = 1,
        ?int $perPage = null,
        string $extras = '',
    ): PagedResult {
        $request = $this->page($page, $perPage);
        $params = [
            'photoset_id' => $photosetId,
            'page' => $request->page,
            'per_page' => $request->perPage,
        ];
        if ($extras !== '') {
            $params['extras'] = $extras;
        }

        $response = $this->call($client, 'flickr.photosets.getPhotos', $params);

        return $this->toPaged($response, 'photoset', ['photo']);
    }
}
