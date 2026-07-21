<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr\Contracts;

use Jooservices\LaravelFlickr\Dto\AppCredentials;
use Jooservices\LaravelFlickr\Exceptions\MissingCredentialsException;

interface AppCredentialsResolverInterface
{
    /**
     * @throws MissingCredentialsException
     */
    public function resolve(): AppCredentials;
}
