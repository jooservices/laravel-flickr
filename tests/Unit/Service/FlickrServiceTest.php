<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Service;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use JOOservices\Flickr\DTO\Common\ApiResponseData;
use JOOservices\Flickr\Flickr;
use JOOservices\LaravelFlickr\Adapters\Photos;
use JOOservices\LaravelFlickr\Adapters\Test as TestAdapter;
use JOOservices\LaravelFlickr\Exceptions\AppNotFoundException;
use JOOservices\LaravelFlickr\Exceptions\TokenNotFoundException;
use JOOservices\LaravelFlickr\Jobs\FlickrRequestJob;
use JOOservices\LaravelFlickr\Service\FlickrService;
use JOOservices\LaravelFlickr\Tests\Support\FlickrNsid;
use JOOservices\LaravelFlickr\Tests\TestCase;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class FlickrServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresMongoDb();
        $this->clearFlickrCollections();
    }

    #[Test]
    public function it_resolves_a_known_adapter_after_anonymous_scope(): void
    {
        $this->storeApp();

        $adapter = app(FlickrService::class)->anonymous()->test;

        $this->assertInstanceOf(TestAdapter::class, $adapter);
        $this->assertNull($adapter->nsid());
        $this->assertSame($this->defaultAppName, $adapter->appName());
    }

    #[Test]
    public function it_resolves_a_known_adapter_after_as_scope(): void
    {
        $token = $this->storeToken();

        $adapter = app(FlickrService::class)->as($token->userNsid)->test;

        $this->assertInstanceOf(TestAdapter::class, $adapter);
        $this->assertSame($token->userNsid, $adapter->nsid());
    }

    #[Test]
    public function it_scopes_to_a_named_connection(): void
    {
        $this->storeApp('backup', 'backup-key', 'backup-secret');
        $token = $this->storeToken(appName: 'backup');

        $adapter = app(FlickrService::class)->connection('backup')->as($token->userNsid)->test;

        $this->assertSame('backup', $adapter->appName());
        $this->assertSame($token->userNsid, $adapter->nsid());
    }

    #[Test]
    public function anonymous_requires_a_registered_app(): void
    {
        $this->expectException(AppNotFoundException::class);

        app(FlickrService::class)->anonymous();
    }

    #[Test]
    public function it_requires_scope_before_adapter_access(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Call ->as($nsid) or ->anonymous() before accessing an adapter.');

        // @phpstan-ignore-next-line intentional unscoped access
        $_ = app(FlickrService::class)->test;
    }

    #[Test]
    public function it_fails_as_when_token_is_missing(): void
    {
        $this->storeApp();
        $this->expectException(TokenNotFoundException::class);

        app(FlickrService::class)->as(FlickrNsid::fake());
    }

    #[Test]
    #[DataProvider('nonFlickrReadPaths')]
    public function it_does_not_expose_activities_logs_or_events(string $property): void
    {
        $this->storeApp();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown Flickr adapter namespace [{$property}].");

        // @phpstan-ignore-next-line properties intentionally unsupported on FlickrService
        $_ = app(FlickrService::class)->anonymous()->{$property};
    }

    #[Test]
    public function it_rejects_unknown_adapter_namespaces(): void
    {
        $this->storeApp();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown Flickr adapter namespace [notARealNamespace].');

        // @phpstan-ignore-next-line intentional unknown adapter
        $_ = app(FlickrService::class)->anonymous()->notARealNamespace;
    }

    #[Test]
    public function call_runs_sync_through_the_shared_job(): void
    {
        Event::fake();
        $this->storeApp();
        $this->fakeFlickrResponses([
            ['method' => ['_content' => 'flickr.test.echo']],
        ]);

        $response = app(FlickrService::class)->anonymous()->call('test', 'echo', ['foo' => 'bar']);

        $this->assertInstanceOf(ApiResponseData::class, $response);
        $this->assertTrue($response->ok);
    }

    #[Test]
    public function call_can_queue_instead_of_running_inline(): void
    {
        Bus::fake();
        $this->storeApp();

        $result = app(FlickrService::class)->anonymous()->call('test', 'echo', queued: true);

        $this->assertNull($result);
        Bus::assertDispatched(FlickrRequestJob::class, function (FlickrRequestJob $job): bool {
            return $job->namespace === 'test'
                && $job->method === 'echo'
                && $job->appName === $this->defaultAppName
                && $job->queued === true;
        });
    }

    #[Test]
    public function get_client_builds_anonymous_and_authenticated_clients(): void
    {
        Event::fake();
        $token = $this->storeToken();

        $anonymous = app(FlickrService::class)->anonymous()->getClient();
        $authenticated = app(FlickrService::class)->as($token->userNsid)->getClient();

        $this->assertInstanceOf(Flickr::class, $anonymous);
        $this->assertInstanceOf(Flickr::class, $authenticated);
    }

    #[Test]
    public function rate_limit_status_uses_the_active_app_key(): void
    {
        $this->storeApp();

        $status = app(FlickrService::class)->anonymous()->rateLimitStatus();

        $this->assertSame(PHP_INT_MAX, $status->limit);
        $this->assertSame(PHP_INT_MAX, $status->remaining);
    }

    #[Test]
    public function call_and_get_client_require_scope(): void
    {
        $svc = app(FlickrService::class);

        try {
            $svc->call('test', 'echo');
            $this->fail('expected LogicException');
        } catch (LogicException) {
        }

        try {
            $svc->getClient();
            $this->fail('expected LogicException');
        } catch (LogicException) {
        }

        $this->assertTrue(true);
    }

    #[Test]
    public function it_rejects_container_misbinds_that_are_not_adapters(): void
    {
        $this->storeApp();
        $this->app->bind(Photos::class, static fn (): object => new \stdClass());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown Flickr adapter');
        // @phpstan-ignore-next-line intentional misbind
        app(FlickrService::class)->anonymous()->photos;
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonFlickrReadPaths(): array
    {
        return [
            'activities' => ['activities'],
            'logs' => ['logs'],
            'events' => ['events'],
        ];
    }
}
