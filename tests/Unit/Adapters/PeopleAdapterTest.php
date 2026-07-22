<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Adapters;

use Illuminate\Support\Facades\Event;
use JOOservices\Flickr\DTO\Common\ApiResponseData;
use JOOservices\LaravelFlickr\Adapters\People;
use JOOservices\LaravelFlickr\Listeners\PersistFlickrData;
use JOOservices\LaravelFlickr\Models\Photo;
use JOOservices\LaravelFlickr\Tests\Support\FlickrNsid;
use JOOservices\LaravelFlickr\Tests\Support\InteractsWithFlickrAdapters;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class PeopleAdapterTest extends TestCase
{
    use InteractsWithFlickrAdapters;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresMongoDb();
        $this->clearFlickrCollections();
    }

    #[Test]
    public function get_photos_calls_flickr_people_get_photos(): void
    {
        Event::fake();
        $token = $this->storeToken();
        $userId = FlickrNsid::fake();
        $photo = $this->fakePhotoItem();

        $response = $this->assertAdapterCall(
            fn () => $this->flickrAs($token)->people->getPhotos($userId, ['per_page' => 5]),
            'flickr.people.getPhotos',
            [
                'photos' => [
                    'page' => 1,
                    'pages' => 1,
                    'perpage' => 5,
                    'total' => 1,
                    'photo' => [$photo],
                ],
            ],
            ['user_id' => $userId, 'per_page' => '5'],
        );

        $this->assertSame($photo['id'], $response->data['photos']['photo'][0]['id'] ?? null);
    }

    #[Test]
    public function get_public_photos_calls_flickr_people_get_public_photos(): void
    {
        Event::fake();
        $userId = FlickrNsid::fake();
        $photo = $this->fakePhotoItem();

        $this->assertAdapterCall(
            fn () => $this->flickrAnonymous()->people->getPublicPhotos($userId, ['per_page' => 3]),
            'flickr.people.getPublicPhotos',
            [
                'photos' => [
                    'page' => 1,
                    'pages' => 1,
                    'perpage' => 3,
                    'total' => 1,
                    'photo' => [$photo],
                ],
            ],
            ['user_id' => $userId, 'per_page' => '3'],
        );
    }

    #[Test]
    public function get_photos_persists_photos_via_listener(): void
    {
        Event::fake();
        $token = $this->storeToken();
        $photo = $this->fakePhotoItem();

        $this->fakeFlickrResponses([[
            'photos' => ['photo' => [$photo]],
        ]]);

        $response = $this->flickrAs($token)->people->getPhotos($token->userNsid);
        $this->assertInstanceOf(ApiResponseData::class, $response);

        app(PersistFlickrData::class)->handle($this->flickrCallCompleted(
            People::NAMESPACE,
            People::METHOD_GET_PHOTOS,
            $token->userNsid,
            ['user_id' => $token->userNsid],
            itemCount: 1,
            response: $response,
        ));

        $row = Photo::query()
            ->where('owner_nsid', $token->userNsid)
            ->where('photo_id', $photo['id'])
            ->first();
        $this->assertNotNull($row);
        $this->assertSame($photo['title'], $row->raw['title'] ?? null);
    }

    #[Test]
    public function persist_skips_anonymous_scope_and_unmatched_methods(): void
    {
        $nsid = FlickrNsid::fake();
        app(People::class, ['appName' => 'default', 'nsid' => null])->persist(
            $this->flickrCallCompleted(People::NAMESPACE, People::METHOD_GET_PHOTOS, null),
        );
        app(People::class, ['appName' => 'default', 'nsid' => $nsid])->persist(
            $this->flickrCallCompleted(People::NAMESPACE, 'getInfo', $nsid),
        );
        $this->assertSame(0, Photo::query()->where('owner_nsid', $nsid)->count());
    }
}
