<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Repositories;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use JOOservices\LaravelFlickr\Events\FlickrContactRemoved;
use JOOservices\LaravelFlickr\Models\Contact;
use Jooservices\LaravelRepository\Contracts\RepositoryInterface;
use Jooservices\LaravelRepository\Repositories\EloquentRepository;
use Jooservices\LaravelRepository\Traits\HasCrud;
use Jooservices\LaravelRepository\Traits\HasFilter;
use Jooservices\LaravelRepository\Traits\HasOrder;
use Jooservices\LaravelRepository\Traits\HasRead;
use Jooservices\LaravelRepository\Traits\HasRequestQuery;

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
        foreach ($items as $item) {
            if (! isset($item['nsid']) || ! is_scalar($item['nsid'])) {
                continue;
            }

            $this->contacts->newQuery()->updateOrCreate(
                ['owner_nsid' => $ownerNsid, 'contact_nsid' => (string) $item['nsid']],
                ['last_seen_at' => now(), 'removed_at' => null, 'raw' => $item],
            );
        }
    }

    public function reconcile(string $ownerNsid, CarbonInterface $completedBefore): int
    {
        $stale = $this->activeForOwner($ownerNsid)
            ->where('last_seen_at', '<', $completedBefore)
            ->get();

        foreach ($stale as $contact) {
            /** @var CarbonInterface $lastSeenAt */
            $lastSeenAt = $contact->last_seen_at;
            $contact->update(['removed_at' => now()]);
            event(new FlickrContactRemoved($ownerNsid, $contact->contact_nsid, $lastSeenAt));
        }

        return $stale->count();
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
