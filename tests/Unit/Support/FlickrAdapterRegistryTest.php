<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Support;

use JOOservices\LaravelFlickr\Adapters\Photos;
use JOOservices\LaravelFlickr\Support\FlickrAdapterRegistry;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class FlickrAdapterRegistryTest extends TestCase
{
    #[Test]
    public function it_maps_known_namespaces_and_lists_classes(): void
    {
        $this->assertTrue(FlickrAdapterRegistry::has('photos'));
        $this->assertSame(Photos::class, FlickrAdapterRegistry::classFor('photos'));
        $this->assertNull(FlickrAdapterRegistry::classFor('groups'));
        $this->assertFalse(FlickrAdapterRegistry::has('groups'));
        $this->assertContains(Photos::class, FlickrAdapterRegistry::classes());
        $this->assertArrayHasKey('contacts', FlickrAdapterRegistry::all());
    }
}
