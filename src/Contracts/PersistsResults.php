<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Contracts;

use JOOservices\LaravelFlickr\Events\FlickrCallCompleted;

interface PersistsResults
{
    public function persist(FlickrCallCompleted $event): void;
}
