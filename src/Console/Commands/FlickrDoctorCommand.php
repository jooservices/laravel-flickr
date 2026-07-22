<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use JOOservices\LaravelFlickr\Contracts\RuntimeSettingsResolverInterface;
use JOOservices\LaravelFlickr\Repositories\AppRepository;
use MongoDB\Laravel\Connection as MongoConnection;
use Throwable;

final class FlickrDoctorCommand extends Command
{
    protected $signature = 'flickr:doctor';

    protected $description = 'Read-only health check for Flickr integration dependencies';

    public function handle(
        AppRepository $apps,
        RuntimeSettingsResolverInterface $runtime,
    ): int {
        $ok = $this->checkDefaultApp($apps, $runtime);
        $ok = $this->checkRedis() && $ok;
        $ok = $this->checkMongo() && $ok;
        $ok = $this->checkQueue($runtime) && $ok;
        $ok = $this->checkIndexes() && $ok;

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function checkDefaultApp(AppRepository $apps, RuntimeSettingsResolverInterface $runtime): bool
    {
        $name = $runtime->defaultConnection();
        $app = $apps->find($name);

        if ($app === null) {
            $this->error("Default Flickr app [{$name}]: missing — run flickr:app:add {$name}");

            return false;
        }

        $this->info("Default Flickr app [{$name}]: OK (".strlen($app->apiKey).' char key)');

        return true;
    }

    private function checkRedis(): bool
    {
        try {
            Redis::connection()->command('ping');
            $this->info('Redis PING: OK');

            return true;
        } catch (Throwable $e) {
            $this->warn('Redis PING: unavailable — '.$e->getMessage());

            return false;
        }
    }

    private function checkMongo(): bool
    {
        try {
            $connection = DB::connection('mongodb');
            if (! $connection instanceof MongoConnection) {
                $this->warn('MongoDB ping: unavailable — mongodb connection is not a MongoDB Laravel connection');

                return false;
            }

            $connection->getMongoClient()->selectDatabase('admin')->command(['ping' => 1]);
            $this->info('MongoDB ping: OK');

            return true;
        } catch (Throwable $e) {
            $this->warn('MongoDB ping: unavailable — '.$e->getMessage());

            return false;
        }
    }

    private function checkQueue(RuntimeSettingsResolverInterface $runtime): bool
    {
        $connection = $runtime->queueConnection() ?? config('queue.default');

        try {
            Queue::connection(is_string($connection) ? $connection : null);
            $this->info('Queue connection ['.(is_string($connection) ? $connection : 'default').']: OK');

            return true;
        } catch (Throwable $e) {
            $this->warn('Queue connection: unavailable — '.$e->getMessage());

            return false;
        }
    }

    private function checkIndexes(): bool
    {
        $definitions = [
            'flickr_apps' => ['name'],
            'flickr_tokens' => ['app_name', 'nsid'],
            'flickr_contacts' => ['owner_nsid', 'contact_nsid'],
            'flickr_photos' => ['owner_nsid', 'photo_id'],
            // Must match flickr:install-indexes key order (owner + group_type + group_id prefix).
            'flickr_photo_groups' => ['owner_nsid', 'group_type', 'group_id', 'photo_id'],
            'flickr_photo_favorites' => ['owner_nsid', 'photo_id'],
        ];

        $allPresent = true;

        try {
            $connection = DB::connection('mongodb');
            if (! $connection instanceof MongoConnection) {
                $this->warn('Index check skipped — mongodb connection is not a MongoDB Laravel connection');

                return false;
            }

            foreach ($definitions as $collection => $fields) {
                $present = $this->indexExists($connection, $collection, $fields);
                if ($present) {
                    $this->info("Index {$collection}: OK");
                } else {
                    $this->warn("Index {$collection}: missing — run flickr:install-indexes");
                    $allPresent = false;
                }
            }
        } catch (Throwable $e) {
            $this->warn('Index check skipped — '.$e->getMessage());

            return false;
        }

        return $allPresent;
    }

    /**
     * @param  list<string>  $fields
     */
    private function indexExists(MongoConnection $connection, string $collection, array $fields): bool
    {
        $expected = [];
        foreach ($fields as $field) {
            $expected[$field] = 1;
        }

        foreach ($connection->getCollection($collection)->listIndexes() as $index) {
            if (! is_object($index) || ! method_exists($index, 'getKey')) {
                continue;
            }

            $key = $index->getKey();
            if ($key === $expected) {
                return true;
            }
        }

        return false;
    }
}
