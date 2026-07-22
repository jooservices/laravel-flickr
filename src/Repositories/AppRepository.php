<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Repositories;

use InvalidArgumentException;
use JOOservices\LaravelFlickr\Dto\FlickrApp;
use JOOservices\LaravelFlickr\Models\App;
use Jooservices\LaravelRepository\Repositories\EloquentRepository;

/**
 * Named Flickr API app storage — keyed by connection name, not Eloquent primary key.
 */
final class AppRepository extends EloquentRepository
{
    public function __construct(
        App $model,
        private readonly TokenRepository $tokens,
    ) {
        parent::__construct($model);
    }

    public function exists(string $name): bool
    {
        return $this->model->newQuery()->where('name', $name)->first() !== null;
    }

    public function find(string $name): ?FlickrApp
    {
        $row = $this->model->newQuery()->where('name', $name)->first();

        if (! $row instanceof App) {
            return null;
        }

        return new FlickrApp($row->name, $row->api_key, $row->api_secret);
    }

    public function store(FlickrApp $app): void
    {
        if (trim($app->name) === '') {
            throw new InvalidArgumentException('Flickr app name is required.');
        }

        $this->model->newQuery()->updateOrCreate(
            ['name' => $app->name],
            [
                'api_key' => $app->apiKey,
                'api_secret' => $app->apiSecret,
            ],
        );
    }

    public function forget(string $name): void
    {
        $this->tokens->forgetByApp($name);
        $this->model->newQuery()->where('name', $name)->delete();
    }
}
