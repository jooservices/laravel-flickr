<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Client;

use Illuminate\Support\Facades\Cache;
use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Flickr\Flickr;
use JOOservices\LaravelConfig\Facades\Config;
use JOOservices\LaravelFlickr\Client\FlickrClientFactory;
use JOOservices\LaravelFlickr\Dto\AppCredentials;
use JOOservices\LaravelFlickr\Exceptions\MissingCredentialsException;
use JOOservices\LaravelFlickr\Tests\Support\FlickrNsid;
use JOOservices\LaravelFlickr\Tests\Support\NonPsr16CacheStore;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

final class FlickrClientFactoryTest extends TestCase
{
    #[Test]
    public function it_builds_authenticated_and_anonymous_clients_against_client_fake(): void
    {
        $this->requiresMongoDb();
        $this->clearFlickrCollections();

        $this->fakeFlickrResponses([
            ['user' => ['id' => FlickrNsid::fake()]],
        ]);

        $factory = app(FlickrClientFactory::class);
        $credentials = new AppCredentials(fake()->sha1(), fake()->sha1());
        $token = $this->storeToken();

        $auth = $factory->authenticated($credentials, $token);
        $anon = $factory->anonymous($credentials);

        $this->assertInstanceOf(Flickr::class, $auth);
        $this->assertInstanceOf(Flickr::class, $anon);

        $response = $auth->raw()->call('flickr.test.login', []);
        $this->assertTrue($response->ok);
        $this->assertNotEmpty(ClientBuilder::recorded());
    }

    #[Test]
    public function it_disables_sdk_rate_limiting_and_wires_cache_ttl(): void
    {
        Config::fake([
            'flickr' => [
                'default_connection' => 'default',
                'cache_ttl_seconds' => 120,
                'rate_limit_enabled' => false,
                'queue_name' => 'flickr',
                'logging_enabled' => true,
                'events_enabled' => true,
                'default_per_page' => 50,
            ],
        ]);
        $this->app->forgetInstance(FlickrClientFactory::class);

        $factory = app(FlickrClientFactory::class);
        $ref = new ReflectionClass($factory);
        $method = $ref->getMethod('flickrConfig');
        $method->setAccessible(true);
        $config = $method->invoke($factory, new AppCredentials('key', 'secret'));

        $this->assertFalse($config->enableRateLimit);
        $this->assertSame(120, $config->publicCacheTtlSeconds);
    }

    #[Test]
    public function it_fails_when_app_credentials_are_empty(): void
    {
        $this->expectException(MissingCredentialsException::class);
        new AppCredentials('', 'secret');
    }

    #[Test]
    public function it_requires_psr16_cache_store(): void
    {
        Config::fake([
            'flickr' => [
                'default_connection' => 'default',
                'cache_store' => 'array',
                'cache_ttl_seconds' => 60,
                'rate_limit_enabled' => false,
                'queue_name' => 'flickr',
            ],
        ]);
        $this->app->forgetInstance(FlickrClientFactory::class);

        Cache::shouldReceive('store')
            ->with('array')
            ->andReturn(new NonPsr16CacheStore());

        $factory = app(FlickrClientFactory::class);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('PSR-16');
        $factory->anonymous(new AppCredentials('k', 's'));
    }
}
