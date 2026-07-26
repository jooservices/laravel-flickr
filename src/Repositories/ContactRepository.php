<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Repositories;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use JOOservices\LaravelFlickr\Models\Contact;
use JOOservices\LaravelFlickr\Service\PersistenceReconcileService;
use JOOservices\LaravelFlickr\Support\MongoBulkUpsert;
use JOOservices\LaravelRepository\Contracts\RepositoryInterface;
use JOOservices\LaravelRepository\Repositories\EloquentRepository;
use JOOservices\LaravelRepository\Traits\HasCrud;
use JOOservices\LaravelRepository\Traits\HasFilter;
use JOOservices\LaravelRepository\Traits\HasOrder;
use JOOservices\LaravelRepository\Traits\HasRead;
use JOOservices\LaravelRepository\Traits\HasRequestQuery;

final class ContactRepository extends EloquentRepository implements RepositoryInterface
{
    use HasCrud;
    use HasFilter;
    use HasOrder;
    use HasRead;
    use HasRequestQuery;

    public function __construct(private readonly Contact $contacts)
    {
        parent::__construct($contacts);
    }

    /** @param  list<array<string, mixed>>  $items */
    public function upsertMany(string $ownerNsid, array $items): void
    {
        $rows = [];
        foreach ($items as $item) {
            if (! isset($item['nsid']) || ! is_scalar($item['nsid'])) {
                continue;
            }

            $rows[] = [
                'filter' => ['owner_nsid' => $ownerNsid, 'contact_nsid' => (string) $item['nsid']],
                'set' => [
                    'owner_nsid' => $ownerNsid,
                    'contact_nsid' => (string) $item['nsid'],
                    'last_seen_at' => now()->toDateTime(),
                    'removed_at' => null,
                    'raw' => $item,
                ],
            ];
        }

        MongoBulkUpsert::upsert($this->contacts, $rows);
    }

    /**
     * Soft-remove stale contacts. Domain events are fired by {@see PersistenceReconcileService}.
     *
     * @return list<array{contact_nsid: string, last_seen_at: CarbonInterface}>
     */
    public function markStaleRemoved(string $ownerNsid, CarbonInterface $completedBefore): array
    {
        $stale = $this->activeForOwner($ownerNsid)
            ->where('last_seen_at', '<', $completedBefore)
            ->get();

        $removed = [];
        foreach ($stale as $contact) {
            /** @var CarbonInterface $lastSeenAt */
            $lastSeenAt = $contact->last_seen_at;
            $contact->update(['removed_at' => now()]);
            $removed[] = [
                'contact_nsid' => $contact->contact_nsid,
                'last_seen_at' => $lastSeenAt,
            ];
        }

        return $removed;
    }

    /** @return Builder<Contact> */
    private function activeForOwner(string $ownerNsid): Builder
    {
        $query = $this->contacts->newQuery();
        $this->contacts->scopeForOwner($query, $ownerNsid);
        $this->contacts->scopeNotRemoved($query);

        return $query;
    }
}
