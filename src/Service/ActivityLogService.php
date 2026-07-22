<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Service;

use Illuminate\Support\Collection;
use JOOservices\LaravelFlickr\Repositories\ActivityLogRepository;

/**
 * Thin read service over activity/system logs. Resolve from the container —
 * not via FlickrService.
 */
final class ActivityLogService
{
    public function __construct(private readonly ActivityLogRepository $repository) {}

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
