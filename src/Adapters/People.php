<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Adapters;

use JOOservices\Flickr\DTO\Common\ApiResponseData;
use JOOservices\LaravelFlickr\Contracts\PersistsResults;
use JOOservices\LaravelFlickr\Events\FlickrCallCompleted;
use JOOservices\LaravelFlickr\Repositories\PhotoRepository;
use JOOservices\LaravelFlickr\Support\FlickrResponseItems;

final class People extends AbstractFlickrAdapter implements PersistsResults
{
    public const string NAMESPACE = 'people';

    public const METHOD_GET_PHOTOS = 'getPhotos';

    public const METHOD_GET_PUBLIC_PHOTOS = 'getPublicPhotos';

    public function __construct(
        string $appName,
        ?string $nsid,
        private readonly PhotoRepository $photoRepository,
    ) {
        parent::__construct($appName, $nsid);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function getPhotos(string $userId, array $params = [], bool $queued = false, bool $bypassCache = false): ?ApiResponseData
    {
        return $this->dispatch(
            self::METHOD_GET_PHOTOS,
            ['user_id' => $userId, ...$params],
            $queued,
            $bypassCache,
            applyDefaultPerPage: true,
        );
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function getPublicPhotos(string $userId, array $params = [], bool $queued = false, bool $bypassCache = false): ?ApiResponseData
    {
        return $this->dispatch(
            self::METHOD_GET_PUBLIC_PHOTOS,
            ['user_id' => $userId, ...$params],
            $queued,
            $bypassCache,
            applyDefaultPerPage: true,
        );
    }

    public function persist(FlickrCallCompleted $event): void
    {
        if (! $event->outcome->ok || ! in_array($event->method, [self::METHOD_GET_PHOTOS, self::METHOD_GET_PUBLIC_PHOTOS], true)) {
            return;
        }

        if ($event->nsid === null) {
            return;
        }

        $this->photoRepository->upsertMany(
            $event->nsid,
            FlickrResponseItems::fromEnvelope($event->outcome->response->data, 'photos', 'photo'),
        );
    }
}
