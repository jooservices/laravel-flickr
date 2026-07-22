<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Adapters;

use Illuminate\Support\Facades\Event;
use JOOservices\Flickr\DTO\Common\ApiResponseData;
use JOOservices\LaravelFlickr\Adapters\Photosets;
use JOOservices\LaravelFlickr\Listeners\PersistFlickrData;
use JOOservices\LaravelFlickr\Models\Photo;
use JOOservices\LaravelFlickr\Models\PhotoGroup;
use JOOservices\LaravelFlickr\Tests\Support\FlickrNsid;
use JOOservices\LaravelFlickr\Tests\Support\InteractsWithFlickrAdapters;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class PhotosetsAdapterTest extends TestCase
{
    use InteractsWithFlickrAdapters;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresMongoDb();
        $this->clearFlickrCollections();
    }

    #[Test]
    public function get_list_calls_flickr_photosets_get_list(): void
    {
        Event::fake();
        $token = $this->storeToken();
        $photosetId = fake()->uuid();

        $this->assertAdapterCall(
            fn () => $this->flickrAs($token)->photosets->getList(['per_page' => 10]),
            'flickr.photosets.getList',
            [
                'photosets' => [
                    'page' => 1,
                    'pages' => 1,
                    'perpage' => 10,
                    'total' => 1,
                    'photoset' => [['id' => $photosetId, 'title' => ['_content' => fake()->words(2, true)]]],
                ],
            ],
            ['per_page' => '10'],
        );
    }

    #[Test]
    public function get_info_calls_flickr_photosets_get_info(): void
    {
        Event::fake();
        $token = $this->storeToken();
        $photosetId = fake()->uuid();

        $this->assertAdapterCall(
            fn () => $this->flickrAs($token)->photosets->getInfo($photosetId),
            'flickr.photosets.getInfo',
            [
                'photoset' => [
                    'id' => $photosetId,
                    'title' => ['_content' => fake()->words(2, true)],
                ],
            ],
            ['photoset_id' => $photosetId],
        );
    }

    #[Test]
    public function get_photos_calls_flickr_photosets_get_photos(): void
    {
        Event::fake();
        $token = $this->storeToken();
        $photosetId = fake()->uuid();
        $photo = $this->fakePhotoItem();

        $this->assertAdapterCall(
            fn () => $this->flickrAs($token)->photosets->getPhotos($photosetId, ['per_page' => 20]),
            'flickr.photosets.getPhotos',
            [
                'photoset' => [
                    'id' => $photosetId,
                    'page' => 1,
                    'pages' => 1,
                    'perpage' => 20,
                    'total' => 1,
                    'photo' => [$photo],
                ],
            ],
            ['photoset_id' => $photosetId, 'per_page' => '20'],
        );
    }

    #[Test]
    public function get_photos_persists_photos_and_group_membership(): void
    {
        Event::fake();
        $token = $this->storeToken();
        $photosetId = fake()->uuid();
        $photo = $this->fakePhotoItem();

        $this->fakeFlickrResponses([[
            'photoset' => [
                'id' => $photosetId,
                'photo' => [$photo],
            ],
        ]]);

        $response = $this->flickrAs($token)->photosets->getPhotos($photosetId);
        $this->assertInstanceOf(ApiResponseData::class, $response);

        app(PersistFlickrData::class)->handle($this->flickrCallCompleted(
            Photosets::NAMESPACE,
            Photosets::METHOD_GET_PHOTOS,
            $token->userNsid,
            ['photoset_id' => $photosetId],
            itemCount: 1,
            response: $response,
        ));

        $this->assertNotNull(
            Photo::query()->where('owner_nsid', $token->userNsid)->where('photo_id', $photo['id'])->first(),
        );
        $membership = PhotoGroup::query()
            ->where('owner_nsid', $token->userNsid)
            ->where('group_type', Photosets::GROUP_TYPE)
            ->where('group_id', $photosetId)
            ->where('photo_id', $photo['id'])
            ->first();
        $this->assertNotNull($membership);
    }

    #[Test]
    public function persist_skips_non_get_photos_and_blank_photoset_id(): void
    {
        $nsid = FlickrNsid::fake();
        $adapter = app(Photosets::class, ['appName' => 'default', 'nsid' => $nsid]);
        $adapter->persist($this->flickrCallCompleted(Photosets::NAMESPACE, Photosets::METHOD_GET_LIST, $nsid));
        $adapter->persist($this->flickrCallCompleted(
            Photosets::NAMESPACE,
            Photosets::METHOD_GET_PHOTOS,
            $nsid,
            params: ['photoset_id' => ''],
        ));
        $this->assertSame(0, PhotoGroup::query()->where('owner_nsid', $nsid)->count());
    }
}
