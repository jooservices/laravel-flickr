<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Support;

use JOOservices\LaravelFlickr\Models\Contact;
use JOOservices\LaravelFlickr\Support\MongoBulkUpsert;
use JOOservices\LaravelFlickr\Tests\Support\FlickrNsid;
use JOOservices\LaravelFlickr\Tests\Support\SqliteMemoryModel;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

final class MongoBulkUpsertTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresMongoDb();
        $this->clearFlickrCollections();
    }

    #[Test]
    public function upsert_writes_rows_in_bulk_and_ignores_empty_batch(): void
    {
        MongoBulkUpsert::upsert(new Contact(), []);
        $this->assertSame(0, Contact::query()->count());

        $owner = FlickrNsid::fake();
        $contact = FlickrNsid::fake();
        MongoBulkUpsert::upsert(new Contact(), [
            [
                'filter' => ['owner_nsid' => $owner, 'contact_nsid' => $contact],
                'set' => [
                    'owner_nsid' => $owner,
                    'contact_nsid' => $contact,
                    'last_seen_at' => now()->toDateTime(),
                    'removed_at' => null,
                    'raw' => ['nsid' => $contact],
                ],
            ],
        ]);

        $this->assertTrue(
            Contact::query()->where('owner_nsid', $owner)->where('contact_nsid', $contact)->exists(),
        );
    }

    #[Test]
    public function upsert_requires_mongo_connection(): void
    {
        config()->set('database.connections.sqlite_mem', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $model = new SqliteMemoryModel();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MongoBulkUpsert requires a MongoDB Laravel connection.');
        MongoBulkUpsert::upsert($model, [
            ['filter' => ['a' => 1], 'set' => ['b' => 2]],
        ]);
    }
}
