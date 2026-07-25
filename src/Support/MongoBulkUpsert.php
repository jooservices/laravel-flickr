<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Support;

use Illuminate\Database\Eloquent\Model;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Laravel\Connection as MongoConnection;
use RuntimeException;

/**
 * Ordered=false bulk upserts for page-level Flickr persistence.
 */
final class MongoBulkUpsert
{
    /**
     * @param  list<array{filter: array<string, mixed>, set: array<string, mixed>}>  $rows
     */
    public static function upsert(Model $model, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $connection = $model->getConnection();
        if (! $connection instanceof MongoConnection) {
            throw new RuntimeException('MongoBulkUpsert requires a MongoDB Laravel connection.');
        }

        $now = new UTCDateTime((int) floor(microtime(true) * 1000));
        $operations = [];
        foreach ($rows as $row) {
            $operations[] = [
                'updateOne' => [
                    $row['filter'],
                    [
                        '$set' => array_merge($row['set'], ['updated_at' => $now]),
                        '$setOnInsert' => ['created_at' => $now],
                    ],
                    ['upsert' => true],
                ],
            ];
        }

        $connection->getCollection($model->getTable())->bulkWrite($operations, ['ordered' => false]);
    }
}
