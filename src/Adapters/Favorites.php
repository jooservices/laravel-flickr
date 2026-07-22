<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Adapters;

use JOOservices\Flickr\DTO\Common\ApiResponseData;
use JOOservices\LaravelFlickr\Contracts\PersistsResults;
use JOOservices\LaravelFlickr\Events\FlickrCallCompleted;
use JOOservices\LaravelFlickr\Repositories\PhotoFavoriteRepository;
use JOOservices\LaravelFlickr\Repositories\PhotoRepository;
use JOOservices\LaravelFlickr\Support\FlickrResponseItems;

final class Favorites extends AbstractFlickrAdapter implements PersistsResults
{
    public const string NAMESPACE = 'favorites';

    public const METHOD_GET_LIST = 'getList';

    public function __construct(
        string $appName,
        ?string $nsid,
        private readonly PhotoRepository $photoRepository,
        private readonly PhotoFavoriteRepository $photoFavoriteRepository,
    ) {
        parent::__construct($appName, $nsid);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function getList(array $params = [], bool $queued = false, bool $bypassCache = false): ?ApiResponseData
    {
        return $this->dispatch(self::METHOD_GET_LIST, $params, $queued, $bypassCache, applyDefaultPerPage: true);
    }

    public function persist(FlickrCallCompleted $event): void
    {
        if (! $event->outcome->ok || $event->nsid === null) {
            return;
        }

        $items = FlickrResponseItems::fromEnvelope($event->outcome->response->data, 'photos', 'photo');
        $this->photoRepository->upsertMany($event->nsid, $items);
        $this->photoFavoriteRepository->markMany(
            $event->nsid,
            FlickrResponseItems::ids($items),
        );
    }
}
