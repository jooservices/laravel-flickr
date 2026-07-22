<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Exceptions;

final class TokenNotFoundException extends LaravelFlickrRuntimeException
{
    public function __construct(string $nsid, string $appName = 'default')
    {
        parent::__construct(
            "No stored Flickr token for NSID [{$nsid}] on connection [{$appName}]. "
            ."Run flickr:oauth:authorize {$appName} to connect it.",
        );
    }
}
