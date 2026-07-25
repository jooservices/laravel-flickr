<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Support;

use JOOservices\LaravelFlickr\Adapters\AbstractFlickrAdapter;
use JOOservices\LaravelFlickr\Adapters\Contacts;
use JOOservices\LaravelFlickr\Adapters\Favorites;
use JOOservices\LaravelFlickr\Adapters\Galleries;
use JOOservices\LaravelFlickr\Adapters\People;
use JOOservices\LaravelFlickr\Adapters\Photos;
use JOOservices\LaravelFlickr\Adapters\Photosets;
use JOOservices\LaravelFlickr\Adapters\Tags;
use JOOservices\LaravelFlickr\Adapters\Test;

/**
 * Single source of truth for Flickr namespace → adapter class mapping.
 */
final class FlickrAdapterRegistry
{
    /**
     * @var array<string, class-string<AbstractFlickrAdapter>>
     */
    private const MAP = [
        'photos' => Photos::class,
        'people' => People::class,
        'contacts' => Contacts::class,
        'photosets' => Photosets::class,
        'galleries' => Galleries::class,
        'favorites' => Favorites::class,
        'test' => Test::class,
        'tags' => Tags::class,
    ];

    /**
     * @return array<string, class-string<AbstractFlickrAdapter>>
     */
    public static function all(): array
    {
        return self::MAP;
    }

    /**
     * @return list<class-string<AbstractFlickrAdapter>>
     */
    public static function classes(): array
    {
        return array_values(self::MAP);
    }

    /**
     * @return class-string<AbstractFlickrAdapter>|null
     */
    public static function classFor(string $namespace): ?string
    {
        return self::MAP[$namespace] ?? null;
    }

    public static function has(string $namespace): bool
    {
        return isset(self::MAP[$namespace]);
    }
}
