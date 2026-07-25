<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Adapters;

use JOOservices\Flickr\DTO\Common\ApiResponseData;

/**
 * Thin adapter over flickr.tags.* (no persistence).
 * Cluster / most-frequent methods intentionally omitted — use FlickrService::call().
 */
final class Tags extends AbstractFlickrAdapter
{
    public const string NAMESPACE = 'tags';

    public const METHOD_GET_LIST_USER = 'getListUser';

    public const METHOD_GET_LIST_USER_POPULAR = 'getListUserPopular';

    public const METHOD_GET_LIST_USER_RAW = 'getListUserRaw';

    public const METHOD_GET_HOT_LIST = 'getHotList';

    public const METHOD_GET_LIST_PHOTO = 'getListPhoto';

    public const METHOD_GET_RELATED = 'getRelated';

    /**
     * @param  array<string, mixed>  $params
     */
    public function getListUser(
        string $userId,
        array $params = [],
        bool $queued = false,
        bool $bypassCache = false,
    ): ?ApiResponseData {
        return $this->dispatch(
            self::METHOD_GET_LIST_USER,
            ['user_id' => $userId, ...$params],
            $queued,
            $bypassCache,
        );
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function getListUserPopular(
        string $userId,
        array $params = [],
        bool $queued = false,
        bool $bypassCache = false,
    ): ?ApiResponseData {
        return $this->dispatch(
            self::METHOD_GET_LIST_USER_POPULAR,
            ['user_id' => $userId, ...$params],
            $queued,
            $bypassCache,
        );
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function getListUserRaw(
        string $userId,
        array $params = [],
        bool $queued = false,
        bool $bypassCache = false,
    ): ?ApiResponseData {
        return $this->dispatch(
            self::METHOD_GET_LIST_USER_RAW,
            ['user_id' => $userId, ...$params],
            $queued,
            $bypassCache,
        );
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function getHotList(
        array $params = [],
        bool $queued = false,
        bool $bypassCache = false,
    ): ?ApiResponseData {
        return $this->dispatch(
            self::METHOD_GET_HOT_LIST,
            $params,
            $queued,
            $bypassCache,
        );
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function getListPhoto(
        string $photoId,
        array $params = [],
        bool $queued = false,
        bool $bypassCache = false,
    ): ?ApiResponseData {
        return $this->dispatch(
            self::METHOD_GET_LIST_PHOTO,
            ['photo_id' => $photoId, ...$params],
            $queued,
            $bypassCache,
        );
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function getRelated(
        string $tag,
        array $params = [],
        bool $queued = false,
        bool $bypassCache = false,
    ): ?ApiResponseData {
        return $this->dispatch(
            self::METHOD_GET_RELATED,
            ['tag' => $tag, ...$params],
            $queued,
            $bypassCache,
        );
    }
}
