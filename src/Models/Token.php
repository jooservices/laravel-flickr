<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * @property string $app_name
 * @property string $nsid
 * @property string $oauth_token
 * @property string $oauth_token_secret
 * @property string|null $username
 * @property string|null $fullname
 */
final class Token extends Model
{
    protected $connection = 'mongodb';

    /** @var string */
    protected $collection = 'flickr_tokens';

    protected $fillable = [
        'app_name',
        'nsid',
        'oauth_token',
        'oauth_token_secret',
        'username',
        'fullname',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'oauth_token' => 'encrypted',
            'oauth_token_secret' => 'encrypted',
        ];
    }
}
