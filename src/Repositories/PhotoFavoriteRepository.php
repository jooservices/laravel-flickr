<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Repositories;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use JOOservices\LaravelFlickr\Models\PhotoFavorite;
use JOOservices\LaravelFlickr\Service\PersistenceReconcileService;
use JOOservices\LaravelFlickr\Support\MongoBulkUpsert;
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
        $rows = [];
        foreach ($photoIds as $photoId) {
            if ($photoId === '') {
                continue;
            }

            $rows[] = [
                'filter' => ['owner_nsid' => $ownerNsid, 'photo_id' => $photoId],
                'set' => [
                    'owner_nsid' => $ownerNsid,
                    'photo_id' => $photoId,
                    'last_seen_at' => now()->toDateTime(),
                    'removed_at' => null,
                ],
            ];
        }

        MongoBulkUpsert::upsert($this->favorites, $rows);
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

    /**
     * Soft-remove stale favorites. Events fired by {@see PersistenceReconcileService}.
     *
     * @return list<array{photo_id: string, last_seen_at: CarbonInterface}>
     */
    public function markStaleRemoved(string $ownerNsid, CarbonInterface $completedBefore): array
    {
        $stale = $this->activeForOwner($ownerNsid)
            ->where('last_seen_at', '<', $completedBefore)
            ->get();

        $removed = [];
        foreach ($stale as $row) {
            /** @var CarbonInterface $lastSeenAt */
            $lastSeenAt = $row->last_seen_at;
            $row->update(['removed_at' => now()]);
            $removed[] = [
                'photo_id' => $row->photo_id,
                'last_seen_at' => $lastSeenAt,
            ];
        }

        return $removed;
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
