<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Adapters;

use Illuminate\Support\Facades\Event;
use JOOservices\LaravelFlickr\Tests\Support\InteractsWithFlickrAdapters;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class PhotosAdapterTest extends TestCase
{
    use InteractsWithFlickrAdapters;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresMongoDb();
        $this->clearFlickrCollections();
    }

    #[Test]
    public function get_popular_calls_flickr_photos_get_popular(): void
    {
        Event::fake();
        $token = $this->storeToken();
        $photo = $this->fakePhotoItem();

        $this->assertAdapterCall(
            fn () => $this->flickrAs($token)->photos->getPopular(['per_page' => 2]),
            'flickr.photos.getPopular',
            [
                'photos' => [
                    'page' => 1,
                    'pages' => 1,
                    'perpage' => 2,
                    'total' => 1,
                    'photo' => [$photo],
                ],
            ],
            ['per_page' => '2'],
        );
    }

    #[Test]
    public function get_info_calls_flickr_photos_get_info(): void
    {
        Event::fake();
        $token = $this->storeToken();
        $photoId = $this->fakePhotoId();

        $this->assertAdapterCall(
            fn () => $this->flickrAs($token)->photos->getInfo($photoId),
            'flickr.photos.getInfo',
            [
                'photo' => [
                    'id' => $photoId,
                    'title' => ['_content' => fake()->sentence(2)],
                ],
            ],
            ['photo_id' => $photoId],
        );
    }

    #[Test]
    public function get_sizes_calls_flickr_photos_get_sizes(): void
    {
        Event::fake();
        $token = $this->storeToken();
        $photoId = $this->fakePhotoId();

        $this->assertAdapterCall(
            fn () => $this->flickrAs($token)->photos->getSizes($photoId),
            'flickr.photos.getSizes',
            [
                'sizes' => [
                    'size' => [
                        ['label' => 'Square', 'width' => 75, 'height' => 75, 'source' => fake()->url()],
                    ],
                ],
            ],
            ['photo_id' => $photoId],
        );
    }

    #[Test]
    public function search_calls_flickr_photos_search(): void
    {
        Event::fake();
        $token = $this->storeToken();
        $text = fake()->word();
        $photo = $this->fakePhotoItem();

        $this->assertAdapterCall(
            fn () => $this->flickrAs($token)->photos->search(['text' => $text, 'per_page' => 1]),
            'flickr.photos.search',
            [
                'photos' => [
                    'page' => 1,
                    'pages' => 1,
                    'perpage' => 1,
                    'total' => 1,
                    'photo' => [$photo],
                ],
            ],
            ['text' => $text, 'per_page' => '1'],
        );
    }

    #[Test]
    public function anonymous_search_is_supported(): void
    {
        Event::fake();
        $photo = $this->fakePhotoItem();

        $this->assertAdapterCall(
            fn () => $this->flickrAnonymous()->photos->search(['text' => fake()->word()]),
            'flickr.photos.search',
            [
                'photos' => [
                    'page' => 1,
                    'pages' => 1,
                    'perpage' => 1,
                    'total' => 1,
                    'photo' => [$photo],
                ],
            ],
        );
    }
}
