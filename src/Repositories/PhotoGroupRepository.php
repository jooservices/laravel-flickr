<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Repositories;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use JOOservices\LaravelFlickr\Models\PhotoGroup;
use JOOservices\LaravelFlickr\Service\PersistenceReconcileService;
use JOOservices\LaravelFlickr\Support\MongoBulkUpsert;
use JOOservices\LaravelRepository\Contracts\RepositoryInterface;
use JOOservices\LaravelRepository\Repositories\EloquentRepository;
use JOOservices\LaravelRepository\Traits\HasCrud;
use JOOservices\LaravelRepository\Traits\HasFilter;
use JOOservices\LaravelRepository\Traits\HasOrder;
use JOOservices\LaravelRepository\Traits\HasRead;
use JOOservices\LaravelRepository\Traits\HasRequestQuery;

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
        $rows = [];
        foreach ($photoIds as $photoId) {
            if ($photoId === '') {
                continue;
            }

            $rows[] = [
                'filter' => [
                    'owner_nsid' => $ownerNsid,
                    'photo_id' => $photoId,
                    'group_type' => $groupType,
                    'group_id' => $groupId,
                ],
                'set' => [
                    'owner_nsid' => $ownerNsid,
                    'photo_id' => $photoId,
                    'group_type' => $groupType,
                    'group_id' => $groupId,
                    'last_seen_at' => now()->toDateTime(),
                    'removed_at' => null,
                ],
            ];
        }

        MongoBulkUpsert::upsert($this->photoGroups, $rows);
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

    /**
     * Soft-remove stale group memberships. Events fired by {@see PersistenceReconcileService}.
     *
     * @return list<array{photo_id: string, last_seen_at: CarbonInterface}>
     */
    public function markStaleRemoved(
        string $ownerNsid,
        string $groupType,
        string $groupId,
        CarbonInterface $completedBefore,
    ): array {
        $stale = $this->activeInGroup($ownerNsid, $groupType, $groupId)
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
