<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Adapters;

use JOOservices\Flickr\DTO\Common\ApiResponseData;
use JOOservices\LaravelFlickr\Contracts\PersistsResults;
use JOOservices\LaravelFlickr\Events\FlickrCallCompleted;
use JOOservices\LaravelFlickr\Repositories\PhotoGroupRepository;
use JOOservices\LaravelFlickr\Repositories\PhotoRepository;
use JOOservices\LaravelFlickr\Support\FlickrResponseItems;

final class Galleries extends AbstractFlickrAdapter implements PersistsResults
{
    public const string NAMESPACE = 'galleries';

    public const METHOD_GET_LIST = 'getList';

    public const METHOD_GET_INFO = 'getInfo';

    public const METHOD_GET_PHOTOS = 'getPhotos';

    public const GROUP_TYPE = 'gallery';

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

    public function getInfo(string $galleryId, bool $queued = false, bool $bypassCache = false): ?ApiResponseData
    {
        return $this->dispatch(self::METHOD_GET_INFO, ['gallery_id' => $galleryId], $queued, $bypassCache);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function getPhotos(string $galleryId, array $params = [], bool $queued = false, bool $bypassCache = false): ?ApiResponseData
    {
        return $this->dispatch(
            self::METHOD_GET_PHOTOS,
            ['gallery_id' => $galleryId, ...$params],
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

        $items = FlickrResponseItems::fromEnvelope($event->outcome->response->data, 'photos', 'photo');
        $this->photoRepository->upsertMany($event->nsid, $items);
        $galleryId = $event->params['gallery_id'] ?? null;
        if (! is_string($galleryId) || $galleryId === '') {
            return;
        }

        $this->photoGroupRepository->attachMany(
            $event->nsid,
            self::GROUP_TYPE,
            $galleryId,
            FlickrResponseItems::ids($items),
        );
    }
}
