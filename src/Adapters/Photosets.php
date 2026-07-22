<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Adapters;

use JOOservices\Flickr\DTO\Common\ApiResponseData;
use JOOservices\LaravelFlickr\Contracts\PersistsResults;
use JOOservices\LaravelFlickr\Events\FlickrCallCompleted;
use JOOservices\LaravelFlickr\Repositories\PhotoGroupRepository;
use JOOservices\LaravelFlickr\Repositories\PhotoRepository;
use JOOservices\LaravelFlickr\Support\FlickrResponseItems;

final class Photosets extends AbstractFlickrAdapter implements PersistsResults
{
    public const string NAMESPACE = 'photosets';

    public const METHOD_GET_LIST = 'getList';

    public const METHOD_GET_INFO = 'getInfo';

    public const METHOD_GET_PHOTOS = 'getPhotos';

    public const GROUP_TYPE = 'photoset';

    public function __construct(
        string $appName,
        ?string $nsid,
        private readonly PhotoRepository $photoRepository,
        private readonly PhotoGroupRepository $photoGroupRepository,
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

    public function getInfo(string $photosetId, bool $queued = false, bool $bypassCache = false): ?ApiResponseData
    {
        return $this->dispatch(self::METHOD_GET_INFO, ['photoset_id' => $photosetId], $queued, $bypassCache);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function getPhotos(string $photosetId, array $params = [], bool $queued = false, bool $bypassCache = false): ?ApiResponseData
    {
        return $this->dispatch(
            self::METHOD_GET_PHOTOS,
            ['photoset_id' => $photosetId, ...$params],
            $queued,
            $bypassCache,
            applyDefaultPerPage: true,
        );
    }

    public function persist(FlickrCallCompleted $event): void
    {
        if (! $event->outcome->ok || $event->method !== self::METHOD_GET_PHOTOS || $event->nsid === null) {
            return;
        }

        $items = FlickrResponseItems::fromEnvelope($event->outcome->response->data, 'photoset', 'photo');
        $this->photoRepository->upsertMany($event->nsid, $items);
        $photosetId = $event->params['photoset_id'] ?? null;
        if (! is_string($photosetId) || $photosetId === '') {
            return;
        }

        $this->photoGroupRepository->attachMany(
            $event->nsid,
            self::GROUP_TYPE,
            $photosetId,
            FlickrResponseItems::ids($items),
        );
    }
}
