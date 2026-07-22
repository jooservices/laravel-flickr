<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * @property string $name
 * @property string $api_key
 * @property string $api_secret
 */
final class App extends Model
{
    protected $connection = 'mongodb';

    /** @var string */
    protected $collection = 'flickr_apps';

    protected $fillable = [
        'name',
        'api_key',
        'api_secret',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'api_secret' => 'encrypted',
        ];
    }
}
