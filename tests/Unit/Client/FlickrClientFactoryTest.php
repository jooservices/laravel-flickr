<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr\Tests\Unit\Client;

use JOOservices\Flickr\Client\FakeFlickrTransport;
use JOOservices\Flickr\Flickr;
use Jooservices\LaravelFlickr\Client\FlickrClientFactory;
use Jooservices\LaravelFlickr\Dto\AppCredentials;
use Jooservices\LaravelFlickr\Dto\OAuthToken;
use Jooservices\LaravelFlickr\Exceptions\MissingCredentialsException;
use Jooservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class FlickrClientFactoryTest extends TestCase
{
    #[Test]
    public function it_builds_authenticated_and_anonymous_clients(): void
    {
        $factory = app(FlickrClientFactory::class);
        $credentials = new AppCredentials('key', 'secret');
        $token = new OAuthToken('tok', 'sec', '12037949629@N01');
        $transport = FakeFlickrTransport::new()->pushJson(['stat' => 'ok', 'user' => ['id' => '12037949629@N01']]);

        $auth = $factory->authenticated($credentials, $token, $transport);
        $anon = $factory->anonymous($credentials, FakeFlickrTransport::new());

        $this->assertInstanceOf(Flickr::class, $auth);
        $this->assertInstanceOf(Flickr::class, $anon);

        $fromConfig = $factory->authenticatedFromConfig($token, FakeFlickrTransport::new());
        $this->assertInstanceOf(Flickr::class, $fromConfig);
    }

    #[Test]
    public function it_fails_when_config_credentials_missing(): void
    {
        config(['laravel-flickr.api_key' => '', 'laravel-flickr.api_secret' => '']);
        $factory = app(FlickrClientFactory::class);

        $this->expectException(MissingCredentialsException::class);
        $factory->anonymousFromConfig();
    }
}
