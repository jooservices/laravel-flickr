<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Repositories;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use JOOservices\LaravelFlickr\Events\FlickrPhotoRemoved;
use JOOservices\LaravelFlickr\Models\Photo;
use Jooservices\LaravelRepository\Contracts\RepositoryInterface;
use Jooservices\LaravelRepository\Repositories\EloquentRepository;
use Jooservices\LaravelRepository\Traits\HasCrud;
use Jooservices\LaravelRepository\Traits\HasFilter;
use Jooservices\LaravelRepository\Traits\HasOrder;
use Jooservices\LaravelRepository\Traits\HasRead;
use Jooservices\LaravelRepository\Traits\HasRequestQuery;

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
        foreach ($items as $item) {
            if (! isset($item['id']) || ! is_scalar($item['id'])) {
                continue;
            }

            $this->photos->newQuery()->updateOrCreate(
                ['owner_nsid' => $ownerNsid, 'photo_id' => (string) $item['id']],
                ['last_seen_at' => now(), 'removed_at' => null, 'raw' => $item],
            );
        }
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

    public function reconcile(string $ownerNsid, CarbonInterface $completedBefore): int
    {
        $stale = $this->activeForOwner($ownerNsid)
            ->where('last_seen_at', '<', $completedBefore)
            ->get();

        foreach ($stale as $photo) {
            /** @var CarbonInterface $lastSeenAt */
            $lastSeenAt = $photo->last_seen_at;
            $photo->update(['removed_at' => now()]);
            event(new FlickrPhotoRemoved($ownerNsid, $photo->photo_id, $lastSeenAt));
        }

        return $stale->count();
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
