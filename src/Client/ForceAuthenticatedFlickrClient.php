<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Client;

use JOOservices\Flickr\Contracts\Client\FlickrClientContract;
use JOOservices\Flickr\DTO\Common\ApiResponseData;
use JOOservices\Flickr\DTO\Common\RequestOptionsData;

/**
 * Decorator: every REST call is OAuth-signed regardless of caller options.
 */
final class ForceAuthenticatedFlickrClient implements FlickrClientContract
{
    public function __construct(
        private readonly FlickrClientContract $inner,
    ) {}

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function call(string $method, array $parameters = [], ?RequestOptionsData $options = null): ApiResponseData
    {
        $options ??= new RequestOptionsData();

        return $this->inner->call($method, $parameters, new RequestOptionsData(
            authenticated: true,
            cache: $options->cache,
            cacheTtl: $options->cacheTtl,
            throwOnApiError: $options->throwOnApiError,
        ));
    }
}
