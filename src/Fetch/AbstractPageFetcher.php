<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr\Fetch;

use JOOservices\Flickr\DTO\Common\ApiResponseData;
use JOOservices\Flickr\DTO\Common\RequestOptionsData;
use JOOservices\Flickr\Flickr;
use Jooservices\LaravelFlickr\Dto\PagedResult;
use Jooservices\LaravelFlickr\Dto\PageRequest;
use Jooservices\LaravelFlickr\Support\PagedResultFactory;
use Jooservices\LaravelFlickr\Support\QueryDefaults;

/**
 * Sync single-page Flickr list calls. Hosts own multi-page walks and queues.
 */
abstract class AbstractPageFetcher
{
    public function __construct(
        protected readonly QueryDefaults $defaults,
    ) {}

    /**
     * @param  array<string, mixed>  $parameters
     */
    protected function call(
        Flickr $client,
        string $method,
        array $parameters,
        bool $authenticated = true,
    ): ApiResponseData {
        return $client->raw()->call(
            $method,
            $parameters,
            new RequestOptionsData(authenticated: $authenticated),
        );
    }

    /**
     * @param  list<string>  $itemKeys
     */
    protected function toPaged(
        ApiResponseData $response,
        string $envelopeKey,
        array $itemKeys,
    ): PagedResult {
        return PagedResultFactory::fromApiResponse($response, $envelopeKey, $itemKeys);
    }

    protected function page(PageRequest|int|null $page, ?int $perPage = null): PageRequest
    {
        if ($page instanceof PageRequest) {
            return $page;
        }

        if (is_int($page)) {
            return new PageRequest($page, $perPage ?? $this->defaults->defaultPageRequest()->perPage);
        }

        $base = $this->defaults->defaultPageRequest();

        return $perPage === null ? $base : new PageRequest($base->page, $perPage);
    }
}
