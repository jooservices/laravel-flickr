<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr\Tests\Unit\Fetch;

use JOOservices\Flickr\Client\FakeFlickrTransport;
use Jooservices\LaravelFlickr\Client\FlickrClientFactory;
use Jooservices\LaravelFlickr\Dto\AppCredentials;
use Jooservices\LaravelFlickr\Dto\OAuthToken;
use Jooservices\LaravelFlickr\Fetch\TokenHealthProbe;
use Jooservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class TokenHealthProbeTest extends TestCase
{
    #[Test]
    public function it_reports_valid_login(): void
    {
        $transport = FakeFlickrTransport::new()->pushJson([
            'stat' => 'ok',
            'user' => ['id' => '12037949629@N01'],
        ]);

        $client = app(FlickrClientFactory::class)->authenticated(
            new AppCredentials('key', 'secret'),
            new OAuthToken('tok', 'sec'),
            $transport,
        );

        $result = app(TokenHealthProbe::class)->probe($client);

        $this->assertTrue($result->valid);
        $this->assertSame('12037949629@N01', $result->userNsid);
    }

    #[Test]
    public function it_reports_invalid_login(): void
    {
        $transport = FakeFlickrTransport::new()->pushJson([
            'stat' => 'fail',
            'code' => 98,
            'message' => 'Invalid auth token',
        ]);

        $client = app(FlickrClientFactory::class)->authenticated(
            new AppCredentials('key', 'secret'),
            new OAuthToken('tok', 'sec'),
            $transport,
        );

        $result = app(TokenHealthProbe::class)->probe($client);

        $this->assertFalse($result->valid);
        $this->assertSame(98, $result->errorCode);
    }
}
