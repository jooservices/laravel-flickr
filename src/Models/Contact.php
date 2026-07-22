<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use MongoDB\Laravel\Eloquent\Model;

/**
 * @property string $owner_nsid
 * @property string $contact_nsid
 * @property CarbonInterface|null $last_seen_at
 * @property CarbonInterface|null $removed_at
 * @property array<string, mixed>|null $raw
 */
final class Contact extends Model
{
    protected $connection = 'mongodb';

    /** @var string */
    protected $collection = 'flickr_contacts';

    protected $fillable = ['owner_nsid', 'contact_nsid', 'last_seen_at', 'removed_at', 'raw'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'removed_at' => 'datetime',
            'raw' => 'array',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForOwner(Builder $query, string $ownerNsid): Builder
    {
        $query->where('owner_nsid', $ownerNsid);

        return $query;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeNotRemoved(Builder $query): Builder
    {
        $query->where('removed_at', null);

        return $query;
    }
}
