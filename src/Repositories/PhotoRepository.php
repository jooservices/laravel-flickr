<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Repositories;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use JOOservices\LaravelFlickr\Models\Photo;
use JOOservices\LaravelFlickr\Service\PersistenceReconcileService;
use JOOservices\LaravelFlickr\Support\MongoBulkUpsert;
use JOOservices\LaravelRepository\Contracts\RepositoryInterface;
use JOOservices\LaravelRepository\Repositories\EloquentRepository;
use JOOservices\LaravelRepository\Traits\HasCrud;
use JOOservices\LaravelRepository\Traits\HasFilter;
use JOOservices\LaravelRepository\Traits\HasOrder;
use JOOservices\LaravelRepository\Traits\HasRead;
use JOOservices\LaravelRepository\Traits\HasRequestQuery;

final class PhotoRepository extends EloquentRepository implements RepositoryInterface
{
    use HasCrud;
    use HasFilter;
    use HasOrder;
    use HasRead;
    use HasRequestQuery;

    public function __construct(
        private readonly Photo $photos,
        private readonly PhotoGroupRepository $photoGroups,
        private readonly PhotoFavoriteRepository $photoFavorites,
    ) {
        parent::__construct($photos);
    }

    /** @param  list<array<string, mixed>>  $items */
    public function upsertMany(string $ownerNsid, array $items): void
    {
        $rows = [];
        foreach ($items as $item) {
            if (! isset($item['id']) || ! is_scalar($item['id'])) {
                continue;
            }

            $rows[] = [
                'filter' => ['owner_nsid' => $ownerNsid, 'photo_id' => (string) $item['id']],
                'set' => [
                    'owner_nsid' => $ownerNsid,
                    'photo_id' => (string) $item['id'],
                    'last_seen_at' => now()->toDateTime(),
                    'removed_at' => null,
                    'raw' => $item,
                ],
            ];
        }

        MongoBulkUpsert::upsert($this->photos, $rows);
    }

    /** @return Collection<int, Photo> */
    public function inPhotoset(string $ownerNsid, string $photosetId): Collection
    {
        return $this->photosForIds($ownerNsid, $this->photoGroups->photoIdsIn($ownerNsid, 'photoset', $photosetId));
    }

    /** @return Collection<int, Photo> */
    public function inGallery(string $ownerNsid, string $galleryId): Collection
    {
        return $this->photosForIds($ownerNsid, $this->photoGroups->photoIdsIn($ownerNsid, 'gallery', $galleryId));
    }

    /** @return Collection<int, Photo> */
    public function favoritedBy(string $ownerNsid): Collection
    {
        return $this->photosForIds($ownerNsid, $this->photoFavorites->photoIdsForOwner($ownerNsid));
    }

    /**
     * Soft-remove stale photos. Domain events are fired by {@see PersistenceReconcileService}.
     *
     * @return list<array{photo_id: string, last_seen_at: CarbonInterface}>
     */
    public function markStaleRemoved(string $ownerNsid, CarbonInterface $completedBefore): array
    {
        $stale = $this->activeForOwner($ownerNsid)
            ->where('last_seen_at', '<', $completedBefore)
            ->get();

        $removed = [];
        foreach ($stale as $photo) {
            /** @var CarbonInterface $lastSeenAt */
            $lastSeenAt = $photo->last_seen_at;
            $photo->update(['removed_at' => now()]);
            $removed[] = [
                'photo_id' => $photo->photo_id,
                'last_seen_at' => $lastSeenAt,
            ];
        }

        return $removed;
    }

    /**
     * @param  list<string>  $photoIds
     * @return Collection<int, Photo>
     */
    private function photosForIds(string $ownerNsid, array $photoIds): Collection
    {
        $ids = array_values(array_filter(
            $photoIds,
            static fn (string $id): bool => $id !== '',
        ));

        if ($ids === []) {
            return collect();
        }

        $out = new Collection();
        // MongoDB native $in keeps a single query without N orWhere clauses.
        $query = $this->forOwner($ownerNsid)->where(['photo_id' => ['$in' => $ids]]);
        foreach ($query->get() as $row) {
            if ($row instanceof Photo) {
                $out->push($row);
            }
        }

        return $out;
    }

    /** @return Builder<Photo> */
    private function activeForOwner(string $ownerNsid): Builder
    {
        $query = $this->forOwner($ownerNsid);
        $this->photos->scopeNotRemoved($query);

        return $query;
    }

    /** @return Builder<Photo> */
    private function forOwner(string $ownerNsid): Builder
    {
        $query = $this->photos->newQuery();
        $this->photos->scopeForOwner($query, $ownerNsid);

        return $query;
    }
}
