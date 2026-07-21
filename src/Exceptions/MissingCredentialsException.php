<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr\Exceptions;

use RuntimeException;

final class MissingCredentialsException extends RuntimeException
{
    public static function emptyAppCredentials(): self
    {
        return new self('Flickr app API key and secret are required.');
    }

    public static function configNotSet(): self
    {
        return new self(
            'Flickr app credentials are not configured. Set FLICKR_API_KEY / FLICKR_API_SECRET '
            .'or pass AppCredentials explicitly.',
        );
    }
}
