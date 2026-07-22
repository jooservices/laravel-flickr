<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Client;

use JOOservices\Flickr\Contracts\Services\RawApiServiceContract;
use JOOservices\Flickr\Flickr;
use JOOservices\Flickr\Services\RawApiService;
use JOOservices\LaravelFlickr\Client\FlickrClientFactory;
use JOOservices\LaravelFlickr\Client\ForceAuthenticatedFlickr;
use JOOservices\LaravelFlickr\Client\ForceAuthenticatedFlickrClient;
use JOOservices\LaravelFlickr\Dto\AppCredentials;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use RuntimeException;

final class ForceAuthenticatedFlickrTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresMongoDb();
        $this->clearFlickrCollections();
    }

    #[Test]
    public function wrap_is_idempotent_for_authenticated_clients(): void
    {
        $token = $this->storeToken();
        $client = app(FlickrClientFactory::class)
            ->authenticated(new AppCredentials($this->defaultApiKey, $this->defaultApiSecret), $token);

        $again = ForceAuthenticatedFlickr::wrap($client);
        $this->assertSame($client, $again);

        $raw = $client->raw();
        $this->assertInstanceOf(RawApiService::class, $raw);
        $prop = new ReflectionProperty(RawApiService::class, 'client');
        $inner = $prop->getValue($raw);
        $this->assertInstanceOf(ForceAuthenticatedFlickrClient::class, $inner);
    }

    #[Test]
    public function wrap_throws_when_raw_is_not_raw_api_service(): void
    {
        $raw = $this->createStub(RawApiServiceContract::class);
        $flickr = $this->createStub(Flickr::class);
        $flickr->method('raw')->willReturn($raw);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected Flickr raw API service');
        ForceAuthenticatedFlickr::wrap($flickr);
    }
}
