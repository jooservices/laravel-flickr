<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Testing\InteractsWithHttpClient;
use JOOservices\Client\Testing\TestResponse;
use JOOservices\Flickr\DTO\Common\ApiResponseData;
use JOOservices\LaravelConfig\ConfigServiceProvider;
use JOOservices\LaravelConfig\Facades\Config;
use JOOservices\LaravelEvents\EventSourcing\Models\StoredEvent;
use JOOservices\LaravelFlickr\Dto\FlickrApp;
use JOOservices\LaravelFlickr\Dto\FlickrCallOutcome;
use JOOservices\LaravelFlickr\Dto\OAuthToken;
use JOOservices\LaravelFlickr\Events\FlickrCallCompleted;
use JOOservices\LaravelFlickr\LaravelFlickrServiceProvider;
use JOOservices\LaravelFlickr\Models\App;
use JOOservices\LaravelFlickr\Models\Contact;
use JOOservices\LaravelFlickr\Models\Photo;
use JOOservices\LaravelFlickr\Models\PhotoFavorite;
use JOOservices\LaravelFlickr\Models\PhotoGroup;
use JOOservices\LaravelFlickr\Models\Token;
use JOOservices\LaravelFlickr\Repositories\AppRepository;
use JOOservices\LaravelFlickr\Repositories\TokenRepository;
use JOOservices\LaravelFlickr\Tests\Support\FlickrNsid;
use JOOservices\LaravelLogging\Models\ActivityLogRecord;
use MongoDB\Laravel\MongoDBServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Throwable;

abstract class TestCase extends Orchestra
{
    use InteractsWithHttpClient;

    protected string $defaultAppName = 'default';

    protected string $defaultApiKey = 'test-api-key';

    protected string $defaultApiSecret = 'test-api-secret';

    protected function getPackageProviders($app): array
    {
        return [
            MongoDBServiceProvider::class,
            ConfigServiceProvider::class,
            LaravelFlickrServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'mongodb');
        $app['config']->set('database.connections.mongodb', [
            'driver' => 'mongodb',
            'dsn' => env('MONGODB_URI', 'mongodb://127.0.0.1:27017'),
            'database' => env('MONGODB_DATABASE', 'jooservices_laravel_flickr_test'),
        ]);
        $app['config']->set('database.redis.client', env('REDIS_CLIENT', 'phpredis'));
        $app['config']->set('database.redis.default', [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => (int) env('REDIS_PORT', 6379),
            'database' => (int) env('REDIS_DB', 15),
        ]);
        $app['config']->set('laravel-logging.connection', 'mongodb');
        $app['config']->set('laravel-logging.collection', 'activity_logs');
    }

    protected function setUp(): void
    {
        parent::setUp();
        ClientBuilder::clearFake();

        Config::fake([
            'flickr' => [
                'default_connection' => $this->defaultAppName,
                'rate_limit_enabled' => false,
                'rate_limit_max_requests_per_hour' => 3300,
                'rate_limit_min_gap_ms' => 333,
                'rate_limit_cooldown_seconds' => 3600,
                'rate_limit_key_prefix' => 'laravel-flickr-test:'.getmypid(),
                'rate_limit_warning_threshold_percent' => 80,
                'cache_ttl_seconds' => 900,
                'oauth_pending_ttl_seconds' => 900,
                'oauth_pending_key_prefix' => 'laravel-flickr-oauth-test:'.getmypid(),
                'queue_name' => 'flickr',
                'logging_enabled' => true,
                'events_enabled' => true,
                'default_per_page' => 50,
            ],
        ]);
    }

    protected function requiresMongoDb(): void
    {
        try {
            DB::connection('mongodb')->getCollection('flickr_tokens')->countDocuments([], ['limit' => 1]);
        } catch (Throwable $exception) {
            $this->failInfra(
                'MongoDB required at '.env('MONGODB_URI', 'mongodb://127.0.0.1:27017').'. '.$exception->getMessage(),
            );
        }
    }

    protected function requiresRedis(): void
    {
        try {
            Redis::connection()->ping();
        } catch (Throwable $exception) {
            $this->failInfra('Redis required at '.env('REDIS_HOST', '127.0.0.1').':'.env('REDIS_PORT', '6379').'. '.$exception->getMessage());
        }
    }

    /**
     * In CI (REQUIRE_TEST_INFRA=1) missing Mongo/Redis fails the suite.
     * Locally, tests that need infra are skipped so pure unit work still runs.
     */
    private function failInfra(string $message): void
    {
        if (filter_var(env('REQUIRE_TEST_INFRA', false), FILTER_VALIDATE_BOOL)) {
            $this->fail($message);
        }

        $this->markTestSkipped($message);
    }

    protected function clearFlickrCollections(): void
    {
        foreach (
            [
                App::class,
                Token::class,
                Contact::class,
                Photo::class,
                PhotoGroup::class,
                PhotoFavorite::class,
                ActivityLogRecord::class,
                StoredEvent::class,
            ] as $model
        ) {
            $model::query()->forceDelete();
        }
    }

    /**
     * Queue Flickr REST JSON responses via jooservices/client's ClientBuilder fake.
     * Production clients then use JooClientTransport against this queue (no repo/transport mocks).
     *
     * @param  list<array<string, mixed>>  $payloads
     */
    protected function fakeFlickrResponses(array $payloads): void
    {
        ClientBuilder::clearFake();
        ClientBuilder::fake(array_map(
            static fn (array $payload): TestResponse => TestResponse::ok(
                array_key_exists('stat', $payload) ? $payload : array_merge(['stat' => 'ok'], $payload),
            ),
            $payloads,
        ));
    }

    protected function storeApp(
        ?string $name = null,
        ?string $apiKey = null,
        ?string $apiSecret = null,
    ): FlickrApp {
        $app = new FlickrApp(
            $name ?? $this->defaultAppName,
            $apiKey ?? $this->defaultApiKey,
            $apiSecret ?? $this->defaultApiSecret,
        );

        app(AppRepository::class)->store($app);

        return $app;
    }

    protected function storeToken(
        ?string $nsid = null,
        ?string $username = null,
        ?string $appName = null,
    ): OAuthToken {
        $appName ??= $this->defaultAppName;
        $this->storeApp($appName);

        $token = new OAuthToken(
            oauthToken: fake()->sha1(),
            oauthTokenSecret: fake()->sha1(),
            userNsid: $nsid ?? FlickrNsid::fake(),
            username: $username ?? fake()->userName(),
            fullname: fake()->name(),
        );

        app(TokenRepository::class)->store($appName, $token);

        return $token;
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $responseData
     */
    protected function flickrCallCompleted(
        string $namespace,
        string $method,
        ?string $nsid = null,
        array $params = [],
        array $responseData = [],
        bool $ok = true,
        int $itemCount = 0,
        ?string $appName = null,
        ?ApiResponseData $response = null,
        float $durationMs = 1.0,
    ): FlickrCallCompleted {
        return new FlickrCallCompleted(
            $namespace,
            $method,
            $appName ?? $this->defaultAppName,
            $nsid,
            $params,
            false,
            new FlickrCallOutcome(
                $ok,
                $itemCount,
                $durationMs,
                quotaRemaining: 100,
                quotaLimit: 3300,
                response: $response ?? new ApiResponseData(ok: $ok, data: $responseData),
            ),
        );
    }
}
