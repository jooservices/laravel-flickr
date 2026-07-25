<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Event;
use JOOservices\Flickr\DTO\Common\ApiResponseData;
use JOOservices\LaravelFlickr\Events\FlickrCallCompleted;
use JOOservices\LaravelFlickr\Events\FlickrCallFailed;
use JOOservices\LaravelFlickr\Events\FlickrCallStarting;
use JOOservices\LaravelFlickr\Exceptions\AppNotFoundException;
use JOOservices\LaravelFlickr\Exceptions\TokenNotFoundException;
use JOOservices\LaravelFlickr\Jobs\FlickrRequestJob;
use JOOservices\LaravelFlickr\Jobs\UniqueFlickrRequestJob;
use JOOservices\LaravelFlickr\Tests\Support\FlickrNsid;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

final class FlickrRequestJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresMongoDb();
        $this->clearFlickrCollections();
    }

    #[Test]
    public function handle_runs_anonymous_call_and_fires_lifecycle_events(): void
    {
        Event::fake([FlickrCallStarting::class, FlickrCallCompleted::class, FlickrCallFailed::class]);
        $this->storeApp();
        $this->fakeFlickrResponses([
            ['method' => ['_content' => 'flickr.test.echo']],
        ]);

        $job = new FlickrRequestJob('test', 'echo', $this->defaultAppName, null, ['foo' => 'bar']);
        $response = app()->call([$job, 'handle']);

        $this->assertInstanceOf(ApiResponseData::class, $response);
        $this->assertTrue($response->ok);
        Event::assertDispatched(FlickrCallStarting::class);
        Event::assertDispatched(FlickrCallCompleted::class, function (FlickrCallCompleted $event): bool {
            return $event->namespace === 'test'
                && $event->method === 'echo'
                && $event->appName === $this->defaultAppName
                && $event->nsid === null
                && $event->outcome->ok;
        });
        Event::assertNotDispatched(FlickrCallFailed::class);
    }

    #[Test]
    public function handle_runs_authenticated_call_with_stored_token(): void
    {
        Event::fake([FlickrCallCompleted::class]);
        $token = $this->storeToken();
        $this->fakeFlickrResponses([
            ['user' => ['id' => $token->userNsid]],
        ]);

        $job = new FlickrRequestJob('test', 'login', $this->defaultAppName, $token->userNsid, []);
        $response = app()->call([$job, 'handle']);

        $this->assertTrue($response->ok);
        Event::assertDispatched(FlickrCallCompleted::class);
    }

    #[Test]
    public function handle_throws_when_app_is_missing(): void
    {
        Event::fake();
        $this->expectException(AppNotFoundException::class);

        $job = new FlickrRequestJob('test', 'echo', 'missing-app', null, []);
        app()->call([$job, 'handle']);
    }

    #[Test]
    public function handle_throws_when_token_is_missing_at_execution(): void
    {
        Event::fake();
        $this->storeApp();
        $this->expectException(TokenNotFoundException::class);

        $job = new FlickrRequestJob('test', 'login', $this->defaultAppName, FlickrNsid::fake(), []);
        app()->call([$job, 'handle']);
    }

    #[Test]
    public function handle_fires_failed_event_and_rethrows_on_transport_errors(): void
    {
        Event::fake();
        $this->storeApp();
        $this->fakeFlickrResponses([]);

        $job = new FlickrRequestJob('test', 'echo', $this->defaultAppName, null, []);

        try {
            app()->call([$job, 'handle']);
            $this->fail('Expected transport failure');
        } catch (RuntimeException) {
            // expected from empty fake queue
        }

        Event::assertDispatched(FlickrCallFailed::class, function (FlickrCallFailed $event): bool {
            return $event->namespace === 'test' && $event->method === 'echo';
        });
        Event::assertNotDispatched(FlickrCallCompleted::class);
    }

    #[Test]
    public function unique_id_includes_app_nsid_method_and_params(): void
    {
        $jobA = new FlickrRequestJob('contacts', 'getList', 'default', '1@N01', ['page' => 1]);
        $jobB = new FlickrRequestJob('contacts', 'getList', 'default', '1@N01', ['page' => 2]);
        $jobC = new FlickrRequestJob('contacts', 'getList', 'backup', '1@N01', ['page' => 1]);

        $this->assertNotSame($jobA->uniqueId(), $jobB->uniqueId());
        $this->assertNotSame($jobA->uniqueId(), $jobC->uniqueId());
        $this->assertStringContainsString('flickr.contacts.getList:default:1@N01:', $jobA->uniqueId());
        $this->assertNotInstanceOf(ShouldBeUnique::class, $jobA);
    }

    #[Test]
    public function unique_job_subclass_is_should_be_unique(): void
    {
        $job = new UniqueFlickrRequestJob(
            'contacts',
            'getList',
            'default',
            '1@N01',
            ['page' => 1],
        );
        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame(60, $job->uniqueFor);
    }

    #[Test]
    public function count_items_from_list_envelope_is_reported_on_completed(): void
    {
        Event::fake();
        $this->storeApp();
        $this->fakeFlickrResponses([
            [
                'photos' => [
                    'photo' => [
                        ['id' => '1'],
                        ['id' => '2'],
                    ],
                ],
            ],
        ]);

        // people.getPublicPhotos is anonymous-capable; response shape still has a list envelope.
        $job = new FlickrRequestJob(
            'people',
            'getPublicPhotos',
            $this->defaultAppName,
            null,
            ['user_id' => '1@N01', 'per_page' => 2],
        );
        app()->call([$job, 'handle']);

        Event::assertDispatched(FlickrCallCompleted::class, function (FlickrCallCompleted $event): bool {
            return $event->outcome->itemCount === 2;
        });
    }

    #[Test]
    public function constructor_is_pure_data_bag_and_exposes_middleware(): void
    {
        $job = new FlickrRequestJob('test', 'echo', 'default', null, []);
        $this->assertNull($job->connection);
        $this->assertNull($job->queue);
        $this->assertNotEmpty($job->middleware());
    }
}
