<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Exceptions;

final class AppNotFoundException extends LaravelFlickrRuntimeException
{
    public function __construct(string $appName)
    {
        parent::__construct(
            "No Flickr API app registered as [{$appName}]. Run flickr:app:add {$appName} first.",
        );
    }
}
