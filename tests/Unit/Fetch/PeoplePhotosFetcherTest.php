<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr\Tests\Unit\Fetch;

use JOOservices\Flickr\Client\FakeFlickrTransport;
use Jooservices\LaravelFlickr\Client\FlickrClientFactory;
use Jooservices\LaravelFlickr\Dto\AppCredentials;
use Jooservices\LaravelFlickr\Dto\OAuthToken;
use Jooservices\LaravelFlickr\Fetch\PeoplePhotosFetcher;
use Jooservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class PeoplePhotosFetcherTest extends TestCase
{
    #[Test]
    public function it_fetches_people_photos_page(): void
    {
        $transport = FakeFlickrTransport::new()->pushJson([
            'stat' => 'ok',
            'photos' => [
                'page' => 1,
                'pages' => 3,
                'perpage' => 1,
                'total' => 3,
                'photo' => [
                    ['id' => 'p1', 'title' => 'One'],
                ],
            ],
        ]);

        $client = app(FlickrClientFactory::class)->authenticated(
            new AppCredentials('key', 'secret'),
            new OAuthToken('tok', 'sec'),
            $transport,
        );

        $page = app(PeoplePhotosFetcher::class)->listPage($client, 'owner@N01', 1, 1);

        $this->assertTrue($page->ok);
        $this->assertTrue($page->hasMorePages());
        $this->assertSame('p1', $page->items[0]['id']);
    }
}
