<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr\Tests\Unit\Fetch;

use JOOservices\Flickr\Client\FakeFlickrTransport;
use Jooservices\LaravelFlickr\Client\FlickrClientFactory;
use Jooservices\LaravelFlickr\Dto\AppCredentials;
use Jooservices\LaravelFlickr\Dto\OAuthToken;
use Jooservices\LaravelFlickr\Fetch\ContactsFetcher;
use Jooservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ContactsFetcherTest extends TestCase
{
    #[Test]
    public function it_fetches_a_contacts_page(): void
    {
        $transport = FakeFlickrTransport::new()->pushJson([
            'stat' => 'ok',
            'contacts' => [
                'page' => 1,
                'pages' => 1,
                'perpage' => 2,
                'total' => 2,
                'contact' => [
                    ['nsid' => '1@N01', 'username' => 'alice'],
                    ['nsid' => '2@N02', 'username' => 'bob'],
                ],
            ],
        ]);

        $client = app(FlickrClientFactory::class)->authenticated(
            new AppCredentials('key', 'secret'),
            new OAuthToken('tok', 'sec', 'me@N01'),
            $transport,
        );

        $page = app(ContactsFetcher::class)->listPage($client, 1, 2);

        $this->assertTrue($page->ok);
        $this->assertSame(2, $page->total);
        $this->assertCount(2, $page->items);
        $this->assertSame('alice', $page->items[0]['username']);
        $this->assertNotSame([], $transport->sentRequests());
    }
}
