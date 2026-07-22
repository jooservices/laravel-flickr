<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Adapters;

use Illuminate\Support\Facades\Event;
use JOOservices\Flickr\DTO\Common\ApiResponseData;
use JOOservices\LaravelFlickr\Adapters\Galleries;
use JOOservices\LaravelFlickr\Listeners\PersistFlickrData;
use JOOservices\LaravelFlickr\Models\Photo;
use JOOservices\LaravelFlickr\Models\PhotoGroup;
use JOOservices\LaravelFlickr\Tests\Support\FlickrNsid;
use JOOservices\LaravelFlickr\Tests\Support\InteractsWithFlickrAdapters;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class GalleriesAdapterTest extends TestCase
{
    use InteractsWithFlickrAdapters;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresMongoDb();
        $this->clearFlickrCollections();
    }

    #[Test]
    public function get_list_calls_flickr_galleries_get_list(): void
    {
        Event::fake();
        $token = $this->storeToken();
        $galleryId = fake()->uuid();

        $this->assertAdapterCall(
            fn () => $this->flickrAs($token)->galleries->getList(['per_page' => 8]),
            'flickr.galleries.getList',
            [
                'galleries' => [
                    'page' => 1,
                    'pages' => 1,
                    'perpage' => 8,
                    'total' => 1,
                    'gallery' => [['id' => $galleryId, 'title' => ['_content' => fake()->words(2, true)]]],
                ],
            ],
            ['per_page' => '8'],
        );
    }

    #[Test]
    public function get_info_calls_flickr_galleries_get_info(): void
    {
        Event::fake();
        $token = $this->storeToken();
        $galleryId = fake()->uuid();

        $this->assertAdapterCall(
            fn () => $this->flickrAs($token)->galleries->getInfo($galleryId),
            'flickr.galleries.getInfo',
            [
                'gallery' => [
                    'id' => $galleryId,
                    'title' => ['_content' => fake()->words(2, true)],
                ],
            ],
            ['gallery_id' => $galleryId],
        );
    }

    #[Test]
    public function get_photos_calls_flickr_galleries_get_photos(): void
    {
        Event::fake();
        $token = $this->storeToken();
        $galleryId = fake()->uuid();
        $photo = $this->fakePhotoItem();

        $this->assertAdapterCall(
            fn () => $this->flickrAs($token)->galleries->getPhotos($galleryId, ['per_page' => 15]),
            'flickr.galleries.getPhotos',
            [
                'photos' => [
                    'page' => 1,
                    'pages' => 1,
                    'perpage' => 15,
                    'total' => 1,
                    'photo' => [$photo],
                ],
            ],
            ['gallery_id' => $galleryId, 'per_page' => '15'],
        );
    }

    #[Test]
    public function get_photos_persists_photos_and_gallery_membership(): void
    {
        Event::fake();
        $token = $this->storeToken();
        $galleryId = fake()->uuid();
        $photo = $this->fakePhotoItem();

        $this->fakeFlickrResponses([[
            'photos' => [
                'photo' => [$photo],
            ],
        ]]);

        $response = $this->flickrAs($token)->galleries->getPhotos($galleryId);
        $this->assertInstanceOf(ApiResponseData::class, $response);

        app(PersistFlickrData::class)->handle($this->flickrCallCompleted(
            Galleries::NAMESPACE,
            Galleries::METHOD_GET_PHOTOS,
            $token->userNsid,
            ['gallery_id' => $galleryId],
            itemCount: 1,
            response: $response,
        ));

        $this->assertNotNull(
            Photo::query()->where('owner_nsid', $token->userNsid)->where('photo_id', $photo['id'])->first(),
        );
        $membership = PhotoGroup::query()
            ->where('owner_nsid', $token->userNsid)
            ->where('group_type', Galleries::GROUP_TYPE)
            ->where('group_id', $galleryId)
            ->where('photo_id', $photo['id'])
            ->first();
        $this->assertNotNull($membership);
    }

    #[Test]
    public function persist_skips_non_get_photos_and_blank_gallery_id(): void
    {
        $nsid = FlickrNsid::fake();
        $adapter = app(Galleries::class, ['appName' => 'default', 'nsid' => $nsid]);
        $adapter->persist($this->flickrCallCompleted(Galleries::NAMESPACE, Galleries::METHOD_GET_LIST, $nsid));
        $adapter->persist($this->flickrCallCompleted(
            Galleries::NAMESPACE,
            Galleries::METHOD_GET_PHOTOS,
            $nsid,
            params: ['gallery_id' => ''],
        ));
        $this->assertSame(0, PhotoGroup::query()->where('owner_nsid', $nsid)->count());
    }
}
