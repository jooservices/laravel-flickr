<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Repositories;

use InvalidArgumentException;
use JOOservices\LaravelFlickr\Dto\FlickrApp;
use JOOservices\LaravelFlickr\Models\App;
use JOOservices\LaravelFlickr\Models\Token;
use JOOservices\LaravelFlickr\Repositories\AppRepository;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class AppRepositoryTest extends TestCase
{
    private AppRepository $apps;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresMongoDb();
        $this->clearFlickrCollections();
        $this->apps = app(AppRepository::class);
    }

    #[Test]
    public function store_find_and_exists_round_trip_with_encryption(): void
    {
        $apiKey = fake()->sha1();
        $secret = fake()->sha1();

        $this->apps->store(new FlickrApp('primary', $apiKey, $secret));

        $this->assertTrue($this->apps->exists('primary'));
        $found = $this->apps->find('primary');
        $this->assertNotNull($found);
        $this->assertSame($apiKey, $found->apiKey);
        $this->assertSame($secret, $found->apiSecret);

        $raw = App::query()->where('name', 'primary')->first();
        $this->assertNotNull($raw);
        $this->assertNotSame($apiKey, $raw->getAttributes()['api_key'] ?? $apiKey);
    }

    #[Test]
    public function forget_removes_the_app_and_its_tokens(): void
    {
        $this->apps->store(new FlickrApp('gone', 'k', 's'));
        $this->storeToken(appName: 'gone');
        $this->assertSame(1, Token::query()->where('app_name', 'gone')->count());

        $this->apps->forget('gone');

        $this->assertFalse($this->apps->exists('gone'));
        $this->assertNull($this->apps->find('gone'));
        $this->assertSame(0, Token::query()->where('app_name', 'gone')->count());
    }

    #[Test]
    public function store_rejects_blank_app_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->apps->store(new FlickrApp('  ', 'k', 's'));
    }
}
