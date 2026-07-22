<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Adapters;

use JOOservices\Flickr\DTO\Common\ApiResponseData;
use JOOservices\LaravelFlickr\Contracts\PersistsResults;
use JOOservices\LaravelFlickr\Events\FlickrCallCompleted;
use JOOservices\LaravelFlickr\Repositories\ContactRepository;
use JOOservices\LaravelFlickr\Support\FlickrResponseItems;

final class Contacts extends AbstractFlickrAdapter implements PersistsResults
{
    public const string NAMESPACE = 'contacts';

    public const METHOD_GET_LIST = 'getList';

    public const METHOD_GET_PUBLIC_LIST = 'getPublicList';

    public function __construct(
        string $appName,
        ?string $nsid,
        private readonly ContactRepository $contactRepository,
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

    /**
     * @param  array<string, mixed>  $params
     */
    public function getPublicList(string $userId, array $params = [], bool $queued = false, bool $bypassCache = false): ?ApiResponseData
    {
        return $this->dispatch(
            self::METHOD_GET_PUBLIC_LIST,
            ['user_id' => $userId, ...$params],
            $queued,
            $bypassCache,
            applyDefaultPerPage: true,
        );
    }

    public function persist(FlickrCallCompleted $event): void
    {
        if (! $event->outcome->ok || $event->method !== self::METHOD_GET_LIST || $event->nsid === null) {
            return;
        }

        $this->contactRepository->upsertMany(
            $event->nsid,
            FlickrResponseItems::fromEnvelope($event->outcome->response->data, 'contacts', 'contact'),
        );
    }
}
