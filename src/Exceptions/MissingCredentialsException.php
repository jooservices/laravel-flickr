<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Exceptions;

use JOOservices\Exceptions\Base\AbstractJOOLogicException;

final class MissingCredentialsException extends AbstractJOOLogicException
{
    public static function emptyAppCredentials(): self
    {
        return new self('Flickr app API key and secret are required.');
    }
}
