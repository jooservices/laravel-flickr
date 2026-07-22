<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Console;

use JOOservices\LaravelFlickr\Models\App;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class FlickrAppAddCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresMongoDb();
        $this->clearFlickrCollections();
    }

    #[Test]
    public function it_stores_a_flickr_app(): void
    {
        $this->artisan('flickr:app:add', [
            'name' => 'primary',
            '--api-key' => 'key-1',
            '--api-secret' => 'secret-1',
        ])->assertSuccessful();

        $row = App::query()->where('name', 'primary')->first();
        $this->assertNotNull($row);
        $this->assertSame('key-1', $row->api_key);
        $this->assertSame('secret-1', $row->api_secret);
    }

    #[Test]
    public function it_rejects_an_empty_name(): void
    {
        $this->artisan('flickr:app:add', [
            'name' => '',
            '--api-key' => 'key-1',
            '--api-secret' => 'secret-1',
        ])
            ->expectsOutputToContain('A non-empty app name is required.')
            ->assertFailed();

        $this->assertSame(0, App::query()->count());
    }

    #[Test]
    public function it_rejects_missing_credentials(): void
    {
        $this->artisan('flickr:app:add', ['name' => 'primary'])
            ->expectsOutputToContain('Both --api-key and --api-secret are required.')
            ->assertFailed();

        $this->assertSame(0, App::query()->count());
    }

    #[Test]
    public function it_updates_an_existing_app(): void
    {
        $this->artisan('flickr:app:add', [
            'name' => 'primary',
            '--api-key' => 'old',
            '--api-secret' => 'old-secret',
        ])->assertSuccessful();

        $this->artisan('flickr:app:add', [
            'name' => 'primary',
            '--api-key' => 'new',
            '--api-secret' => 'new-secret',
        ])->assertSuccessful();

        $this->assertSame(1, App::query()->count());
        $row = App::query()->where('name', 'primary')->first();
        $this->assertNotNull($row);
        $this->assertSame('new', $row->api_key);
    }
}
