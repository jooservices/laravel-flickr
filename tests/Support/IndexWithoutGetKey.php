<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Support;

/** Mongo index stand-in without getKey(). */
final class IndexWithoutGetKey
{
    public function marker(): string
    {
        return 'no-get-key';
    }
}
