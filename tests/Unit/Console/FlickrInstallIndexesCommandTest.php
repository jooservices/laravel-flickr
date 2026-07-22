<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Console;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use JOOservices\LaravelFlickr\Console\Commands\FlickrInstallIndexesCommand;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

final class FlickrInstallIndexesCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresMongoDb();
    }

    #[Test]
    public function it_installs_mongodb_indexes(): void
    {
        $this->artisan('flickr:install-indexes')
            ->expectsOutputToContain('Flickr MongoDB indexes installed.')
            ->assertSuccessful();
    }

    #[Test]
    public function it_requires_a_mongodb_laravel_connection(): void
    {
        $nonMongo = $this->createStub(Connection::class);
        DB::shouldReceive('connection')->with('mongodb')->andReturn($nonMongo);

        $command = app(FlickrInstallIndexesCommand::class);
        $command->setLaravel($this->app);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must use the MongoDB Laravel driver');
        $command->handle();
    }
}
