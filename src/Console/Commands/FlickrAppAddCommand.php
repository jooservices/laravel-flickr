<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Console\Commands;

use Illuminate\Console\Command;
use JOOservices\LaravelFlickr\Dto\FlickrApp;
use JOOservices\LaravelFlickr\Repositories\AppRepository;

final class FlickrAppAddCommand extends Command
{
    protected $signature = 'flickr:app:add
                            {name : Connection name for this Flickr API app}
                            {--api-key= : Flickr application API key}
                            {--api-secret= : Flickr application API secret}';

    protected $description = 'Register or update a Flickr API app (connection) in MongoDB';

    public function handle(AppRepository $apps): int
    {
        $name = $this->argument('name');
        $apiKey = $this->option('api-key');
        $apiSecret = $this->option('api-secret');

        if (! is_string($name) || $name === '') {
            $this->error('A non-empty app name is required.');

            return self::FAILURE;
        }

        if (! is_string($apiKey) || $apiKey === '' || ! is_string($apiSecret) || $apiSecret === '') {
            $this->error('Both --api-key and --api-secret are required.');

            return self::FAILURE;
        }

        $apps->store(new FlickrApp($name, $apiKey, $apiSecret));
        $this->info("Flickr API app [{$name}] stored.");

        return self::SUCCESS;
    }
}
