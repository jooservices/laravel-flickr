<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Support;

/** Intentionally does not implement PSR-16. */
final class NonPsr16CacheStore
{
    public function marker(): string
    {
        return 'non-psr16';
    }
}
