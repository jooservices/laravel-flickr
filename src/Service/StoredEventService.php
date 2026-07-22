<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Service;

use Illuminate\Support\Collection;
use JOOservices\LaravelFlickr\Repositories\EventRepository;

/**
 * Thin read service over stored events. Resolve from the container —
 * not via FlickrService. Named StoredEventService to avoid clashing with
 * laravel-events' write-side EventService.
 */
final class StoredEventService
{
    public function __construct(private readonly EventRepository $repository) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function filter(array $filters): static
    {
        $this->repository->filter($filters);

        return $this;
    }

    /**
     * @param  array<string, 'asc'|'desc'>  $orders
     */
    public function orderBy(array $orders): static
    {
        $this->repository->orderBy($orders);

        return $this;
    }

    /**
     * @return Collection<int, mixed>
     */
    public function get(): Collection
    {
        return $this->repository->get();
    }
}
