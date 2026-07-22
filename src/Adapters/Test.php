<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Adapters;

use JOOservices\Flickr\DTO\Common\ApiResponseData;

final class Test extends AbstractFlickrAdapter
{
    public const string NAMESPACE = 'test';

    public const METHOD_LOGIN = 'login';

    public const METHOD_ECHO = 'echo';

    public const METHOD_NULL = 'null';

    public function login(bool $queued = false): ?ApiResponseData
    {
        return $this->dispatch(self::METHOD_LOGIN, [], $queued, bypassCache: true);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function echo(array $params = [], bool $queued = false): ?ApiResponseData
    {
        return $this->dispatch(self::METHOD_ECHO, $params, $queued, bypassCache: true);
    }

    public function null(bool $queued = false): ?ApiResponseData
    {
        return $this->dispatch(self::METHOD_NULL, [], $queued, bypassCache: true);
    }
}
