<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Adapters;

use Illuminate\Support\Facades\Event;
use JOOservices\LaravelFlickr\Tests\Support\InteractsWithFlickrAdapters;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class TestAdapterTest extends TestCase
{
    use InteractsWithFlickrAdapters;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresMongoDb();
        $this->clearFlickrCollections();
    }

    #[Test]
    public function login_calls_flickr_test_login(): void
    {
        Event::fake();
        $token = $this->storeToken();

        $response = $this->assertAdapterCall(
            fn () => $this->flickrAs($token)->test->login(),
            'flickr.test.login',
            [
                'user' => [
                    'id' => $token->userNsid,
                    'username' => ['_content' => $token->username],
                ],
            ],
            [],
        );

        $this->assertSame($token->userNsid, $response->data['user']['id'] ?? null);
    }

    #[Test]
    public function echo_calls_flickr_test_echo(): void
    {
        Event::fake();
        $token = $this->storeToken();
        $payload = ['foo' => fake()->word()];

        $this->assertAdapterCall(
            fn () => $this->flickrAs($token)->test->echo($payload),
            'flickr.test.echo',
            [
                'method' => ['_content' => 'flickr.test.echo'],
                'foo' => ['_content' => $payload['foo']],
            ],
            $payload,
        );
    }

    #[Test]
    public function null_calls_flickr_test_null(): void
    {
        Event::fake();
        $token = $this->storeToken();

        $this->assertAdapterCall(
            fn () => $this->flickrAs($token)->test->null(),
            'flickr.test.null',
            [],
        );
    }

    #[Test]
    public function anonymous_echo_is_supported(): void
    {
        Event::fake();

        $this->assertAdapterCall(
            fn () => $this->flickrAnonymous()->test->echo(['ping' => 'pong']),
            'flickr.test.echo',
            [
                'method' => ['_content' => 'flickr.test.echo'],
                'ping' => ['_content' => 'pong'],
            ],
            ['ping' => 'pong'],
        );
    }
}
