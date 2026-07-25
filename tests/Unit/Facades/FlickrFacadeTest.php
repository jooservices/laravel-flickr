<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Facades;

use JOOservices\LaravelFlickr\Facades\Flickr;
use JOOservices\LaravelFlickr\Service\FlickrService;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class FlickrFacadeTest extends TestCase
{
    #[Test]
    public function facade_resolves_to_flickr_service_singleton(): void
    {
        $this->assertSame(app(FlickrService::class), Flickr::getFacadeRoot());
        $this->assertSame(app(FlickrService::class), app('flickr'));
    }
}
