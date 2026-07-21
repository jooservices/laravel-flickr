<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr\Tests\Unit\Fetch;

use JOOservices\Flickr\Client\FakeFlickrTransport;
use Jooservices\LaravelFlickr\Client\FlickrClientFactory;
use Jooservices\LaravelFlickr\Dto\AppCredentials;
use Jooservices\LaravelFlickr\Dto\OAuthToken;
use Jooservices\LaravelFlickr\Fetch\FavoritesFetcher;
use Jooservices\LaravelFlickr\Fetch\GalleriesFetcher;
use Jooservices\LaravelFlickr\Fetch\PhotosetsFetcher;
use Jooservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class CollectionFetchersTest extends TestCase
{
    #[Test]
    public function photosets_galleries_and_favorites_return_pages(): void
    {
        $credentials = new AppCredentials('key', 'secret');
        $token = new OAuthToken('tok', 'sec');
        $factory = app(FlickrClientFactory::class);

        $photosetsClient = $factory->authenticated(
            $credentials,
            $token,
            FakeFlickrTransport::new()->pushJson([
                'stat' => 'ok',
                'photosets' => [
                    'page' => 1,
                    'pages' => 1,
                    'perpage' => 1,
                    'total' => 1,
                    'photoset' => [['id' => 'set1']],
                ],
            ]),
        );
        $setPage = app(PhotosetsFetcher::class)->listPage($photosetsClient, 'u@N01');
        $this->assertTrue($setPage->ok);
        $this->assertSame('set1', $setPage->items[0]['id']);

        $galleryClient = $factory->authenticated(
            $credentials,
            $token,
            FakeFlickrTransport::new()->pushJson([
                'stat' => 'ok',
                'galleries' => [
                    'page' => 1,
                    'pages' => 1,
                    'perpage' => 1,
                    'total' => 1,
                    'gallery' => [['id' => 'g1']],
                ],
            ]),
        );
        $galleryPage = app(GalleriesFetcher::class)->listPage($galleryClient, 'u@N01');
        $this->assertSame('g1', $galleryPage->items[0]['id']);

        $favClient = $factory->authenticated(
            $credentials,
            $token,
            FakeFlickrTransport::new()->pushJson([
                'stat' => 'ok',
                'photos' => [
                    'page' => 1,
                    'pages' => 1,
                    'perpage' => 1,
                    'total' => 1,
                    'photo' => [['id' => 'fav1']],
                ],
            ]),
        );
        $favPage = app(FavoritesFetcher::class)->listPage($favClient, 'u@N01');
        $this->assertSame('fav1', $favPage->items[0]['id']);
    }
}
