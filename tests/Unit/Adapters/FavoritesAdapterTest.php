<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Adapters;

use Illuminate\Support\Facades\Event;
use JOOservices\Flickr\DTO\Common\ApiResponseData;
use JOOservices\LaravelFlickr\Adapters\Favorites;
use JOOservices\LaravelFlickr\Listeners\PersistFlickrData;
use JOOservices\LaravelFlickr\Models\Photo;
use JOOservices\LaravelFlickr\Models\PhotoFavorite;
use JOOservices\LaravelFlickr\Tests\Support\InteractsWithFlickrAdapters;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class FavoritesAdapterTest extends TestCase
{
    use InteractsWithFlickrAdapters;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresMongoDb();
        $this->clearFlickrCollections();
    }

    #[Test]
    public function get_list_calls_flickr_favorites_get_list(): void
    {
        Event::fake();
        $token = $this->storeToken();
        $photo = $this->fakePhotoItem();

        $this->assertAdapterCall(
            fn () => $this->flickrAs($token)->favorites->getList(['per_page' => 12]),
            'flickr.favorites.getList',
            [
                'photos' => [
                    'page' => 1,
                    'pages' => 1,
                    'perpage' => 12,
                    'total' => 1,
                    'photo' => [$photo],
                ],
            ],
            ['per_page' => '12'],
        );
    }

    #[Test]
    public function get_list_persists_photos_and_favorites(): void
    {
        Event::fake();
        $token = $this->storeToken();
        $photo = $this->fakePhotoItem();

        $this->fakeFlickrResponses([[
            'photos' => [
                'photo' => [$photo],
            ],
        ]]);

        $response = $this->flickrAs($token)->favorites->getList();
        $this->assertInstanceOf(ApiResponseData::class, $response);

        app(PersistFlickrData::class)->handle($this->flickrCallCompleted(
            Favorites::NAMESPACE,
            Favorites::METHOD_GET_LIST,
            $token->userNsid,
            itemCount: 1,
            response: $response,
        ));

        $this->assertNotNull(
            Photo::query()->where('owner_nsid', $token->userNsid)->where('photo_id', $photo['id'])->first(),
        );
        $favorite = PhotoFavorite::query()
            ->where('owner_nsid', $token->userNsid)
            ->where('photo_id', $photo['id'])
            ->first();
        $this->assertNotNull($favorite);
        $this->assertNull($favorite->removed_at);
    }

    #[Test]
    public function persist_skips_when_nsid_is_null(): void
    {
        app(Favorites::class, ['appName' => 'default', 'nsid' => null])->persist(
            $this->flickrCallCompleted(Favorites::NAMESPACE, Favorites::METHOD_GET_LIST, null),
        );
        $this->assertSame(0, PhotoFavorite::query()->count());
    }
}
