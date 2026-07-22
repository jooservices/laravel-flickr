<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Repositories;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use JOOservices\LaravelFlickr\Events\FlickrPhotoGroupRemoved;
use JOOservices\LaravelFlickr\Models\PhotoGroup;
use Jooservices\LaravelRepository\Contracts\RepositoryInterface;
use Jooservices\LaravelRepository\Repositories\EloquentRepository;
use Jooservices\LaravelRepository\Traits\HasCrud;
use Jooservices\LaravelRepository\Traits\HasFilter;
use Jooservices\LaravelRepository\Traits\HasOrder;
use Jooservices\LaravelRepository\Traits\HasRead;
use Jooservices\LaravelRepository\Traits\HasRequestQuery;

final class PhotoGroupRepository extends EloquentRepository implements RepositoryInterface
{
    use HasCrud;
    use HasFilter;
    use HasOrder;
    use HasRead;
    use HasRequestQuery;

    public function __construct(private readonly PhotoGroup $photoGroups)
    {
        parent::__construct($photoGroups);
    }

    /** @param  list<string>  $photoIds */
    public function attachMany(string $ownerNsid, string $groupType, string $groupId, array $photoIds): void
    {
        foreach ($photoIds as $photoId) {
            $this->photoGroups->newQuery()->updateOrCreate(
                [
                    'owner_nsid' => $ownerNsid,
                    'photo_id' => $photoId,
                    'group_type' => $groupType,
                    'group_id' => $groupId,
                ],
                ['last_seen_at' => now(), 'removed_at' => null],
            );
        }
    }

    /** @return list<string> */
    public function photoIdsIn(string $ownerNsid, string $groupType, string $groupId): array
    {
        $ids = [];
        foreach ($this->activeInGroup($ownerNsid, $groupType, $groupId)->pluck('photo_id') as $photoId) {
            if (is_string($photoId) && $photoId !== '') {
                $ids[] = $photoId;
            }
        }

        return $ids;
    }

    public function reconcile(string $ownerNsid, string $groupType, string $groupId, CarbonInterface $completedBefore): int
    {
        $stale = $this->activeInGroup($ownerNsid, $groupType, $groupId)
            ->where('last_seen_at', '<', $completedBefore)
            ->get();

        foreach ($stale as $row) {
            /** @var CarbonInterface $lastSeenAt */
            $lastSeenAt = $row->last_seen_at;
            $row->update(['removed_at' => now()]);
            event(new FlickrPhotoGroupRemoved($ownerNsid, $row->photo_id, $groupType, $groupId, $lastSeenAt));
        }

        return $stale->count();
    }

    /** @return Builder<PhotoGroup> */
    private function activeInGroup(string $ownerNsid, string $groupType, string $groupId): Builder
    {
        $query = $this->photoGroups->newQuery();
        $this->photoGroups->scopeForOwner($query, $ownerNsid);
        $this->photoGroups->scopeInGroup($query, $groupType, $groupId);
        $this->photoGroups->scopeNotRemoved($query);

        return $query;
    }
}
