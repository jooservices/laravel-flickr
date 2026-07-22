<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Adapters;

use JOOservices\Flickr\DTO\Common\ApiResponseData;

final class Photos extends AbstractFlickrAdapter
{
    public const string NAMESPACE = 'photos';

    public const METHOD_GET_POPULAR = 'getPopular';

    public const METHOD_GET_INFO = 'getInfo';

    public const METHOD_GET_SIZES = 'getSizes';

    public const METHOD_SEARCH = 'search';

    /**
     * @param  array<string, mixed>  $params
     */
    public function getPopular(array $params = [], bool $queued = false, bool $bypassCache = false): ?ApiResponseData
    {
        return $this->dispatch(self::METHOD_GET_POPULAR, $params, $queued, $bypassCache, applyDefaultPerPage: true);
    }

    public function getInfo(string $photoId, bool $queued = false, bool $bypassCache = false): ?ApiResponseData
    {
        return $this->dispatch(self::METHOD_GET_INFO, ['photo_id' => $photoId], $queued, $bypassCache);
    }

    public function getSizes(string $photoId, bool $queued = false, bool $bypassCache = false): ?ApiResponseData
    {
        return $this->dispatch(self::METHOD_GET_SIZES, ['photo_id' => $photoId], $queued, $bypassCache);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function search(array $params = [], bool $queued = false, bool $bypassCache = false): ?ApiResponseData
    {
        return $this->dispatch(self::METHOD_SEARCH, $params, $queued, $bypassCache, applyDefaultPerPage: true);
    }
}
