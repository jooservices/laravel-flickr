<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Repositories;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use JOOservices\LaravelFlickr\Events\FlickrPhotoUnfavorited;
use JOOservices\LaravelFlickr\Models\PhotoFavorite;
use Jooservices\LaravelRepository\Contracts\RepositoryInterface;
use Jooservices\LaravelRepository\Repositories\EloquentRepository;
use Jooservices\LaravelRepository\Traits\HasCrud;
use Jooservices\LaravelRepository\Traits\HasFilter;
use Jooservices\LaravelRepository\Traits\HasOrder;
use Jooservices\LaravelRepository\Traits\HasRead;
use Jooservices\LaravelRepository\Traits\HasRequestQuery;

final class PhotoFavoriteRepository extends EloquentRepository implements RepositoryInterface
{
    use HasCrud;
    use HasFilter;
    use HasOrder;
    use HasRead;
    use HasRequestQuery;

    public function __construct(private readonly PhotoFavorite $favorites)
    {
        parent::__construct($favorites);
    }

    /** @param  list<string>  $photoIds */
    public function markMany(string $ownerNsid, array $photoIds): void
    {
        foreach ($photoIds as $photoId) {
            $this->favorites->newQuery()->updateOrCreate(
                ['owner_nsid' => $ownerNsid, 'photo_id' => $photoId],
                ['last_seen_at' => now(), 'removed_at' => null],
            );
        }
    }

    /** @return list<string> */
    public function photoIdsForOwner(string $ownerNsid): array
    {
        $ids = [];
        foreach ($this->activeForOwner($ownerNsid)->pluck('photo_id') as $photoId) {
            if (is_string($photoId) && $photoId !== '') {
                $ids[] = $photoId;
            }
        }

        return $ids;
    }

    public function reconcile(string $ownerNsid, CarbonInterface $completedBefore): int
    {
        $stale = $this->activeForOwner($ownerNsid)
            ->where('last_seen_at', '<', $completedBefore)
            ->get();

        foreach ($stale as $row) {
            /** @var CarbonInterface $lastSeenAt */
            $lastSeenAt = $row->last_seen_at;
            $row->update(['removed_at' => now()]);
            event(new FlickrPhotoUnfavorited($ownerNsid, $row->photo_id, $lastSeenAt));
        }

        return $stale->count();
    }

    /** @return Builder<PhotoFavorite> */
    private function activeForOwner(string $ownerNsid): Builder
    {
        $query = $this->favorites->newQuery();
        $this->favorites->scopeForOwner($query, $ownerNsid);
        $this->favorites->scopeNotRemoved($query);

        return $query;
    }
}
