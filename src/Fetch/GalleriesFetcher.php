<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr\Fetch;

use JOOservices\Flickr\Flickr;
use Jooservices\LaravelFlickr\Dto\PagedResult;
use Jooservices\LaravelFlickr\Dto\PageRequest;

final class GalleriesFetcher extends AbstractPageFetcher
{
    public function listPage(
        Flickr $client,
        string $userId,
        PageRequest|int $page = 1,
        ?int $perPage = null,
    ): PagedResult {
        $request = $this->page($page, $perPage);
        $response = $this->call($client, 'flickr.galleries.getList', [
            'user_id' => $userId,
            'page' => $request->page,
            'per_page' => $request->perPage,
        ]);

        return $this->toPaged($response, 'galleries', ['gallery']);
    }

    public function photosPage(
        Flickr $client,
        string $galleryId,
        PageRequest|int $page = 1,
        ?int $perPage = null,
        string $extras = '',
    ): PagedResult {
        $request = $this->page($page, $perPage);
        $params = [
            'gallery_id' => $galleryId,
            'page' => $request->page,
            'per_page' => $request->perPage,
        ];
        if ($extras !== '') {
            $params['extras'] = $extras;
        }

        $response = $this->call($client, 'flickr.galleries.getPhotos', $params);

        return $this->toPaged($response, 'photos', ['photo']);
    }
}
