<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Adapters;

use JOOservices\Flickr\DTO\Common\ApiResponseData;
use JOOservices\LaravelFlickr\Support\FlickrJobDispatcher;

abstract class AbstractFlickrAdapter
{
    public const string NAMESPACE = '';

    public function __construct(
        protected readonly string $appName,
        protected readonly ?string $nsid,
    ) {}

    public function nsid(): ?string
    {
        return $this->nsid;
    }

    public function appName(): string
    {
        return $this->appName;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function dispatch(
        string $method,
        array $params,
        bool $queued,
        bool $bypassCache,
        bool $applyDefaultPerPage = false,
    ): ?ApiResponseData {
        return FlickrJobDispatcher::dispatch(
            static::NAMESPACE,
            $method,
            $this->appName,
            $this->nsid,
            $params,
            $queued,
            $bypassCache,
            $applyDefaultPerPage,
        );
    }
}
