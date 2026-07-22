<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use MongoDB\Laravel\Connection as MongoConnection;
use RuntimeException;

final class FlickrInstallIndexesCommand extends Command
{
    protected $signature = 'flickr:install-indexes';

    protected $description = 'Create MongoDB indexes for Flickr persistence collections';

    public function handle(): int
    {
        $connection = DB::connection('mongodb');
        if (! $connection instanceof MongoConnection) {
            throw new RuntimeException('Database connection [mongodb] must use the MongoDB Laravel driver.');
        }

        $this->createUniqueIndex($connection, 'flickr_apps', ['name' => 1]);
        $this->createUniqueIndex($connection, 'flickr_tokens', ['app_name' => 1, 'nsid' => 1]);
        $this->createUniqueIndex($connection, 'flickr_contacts', ['owner_nsid' => 1, 'contact_nsid' => 1]);
        $this->createUniqueIndex($connection, 'flickr_photos', ['owner_nsid' => 1, 'photo_id' => 1]);
        // Prefix matches dominant access: owner + group_type + group_id (photo_id varies).
        $this->createUniqueIndex($connection, 'flickr_photo_groups', ['owner_nsid' => 1, 'group_type' => 1, 'group_id' => 1, 'photo_id' => 1]);
        $this->createUniqueIndex($connection, 'flickr_photo_favorites', ['owner_nsid' => 1, 'photo_id' => 1]);

        $this->info('Flickr MongoDB indexes installed.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $keys
     */
    private function createUniqueIndex(MongoConnection $connection, string $collection, array $keys): void
    {
        $connection->getCollection($collection)->createIndex($keys, ['unique' => true]);
        $this->line("  ✓ {$collection}");
    }
}
