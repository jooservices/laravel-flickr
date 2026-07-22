<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Exceptions;

use JOOservices\Exceptions\Base\AbstractJOORuntimeException;

class LaravelFlickrRuntimeException extends AbstractJOORuntimeException
{
    /** Package domain marker (also keeps empty base class coverageable). */
    public function packageDomain(): string
    {
        return 'laravel-flickr';
    }
}
