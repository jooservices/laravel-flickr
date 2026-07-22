<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Repositories;

use JOOservices\LaravelFlickr\Models\Photo;
use JOOservices\LaravelFlickr\Repositories\PhotoFavoriteRepository;
use JOOservices\LaravelFlickr\Repositories\PhotoGroupRepository;
use JOOservices\LaravelFlickr\Repositories\PhotoRepository;
use JOOservices\LaravelFlickr\Tests\Support\FlickrNsid;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class PhotoRepositoryQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresMongoDb();
        $this->clearFlickrCollections();
    }

    #[Test]
    public function in_photoset_loads_photos_from_group_pivot_ids(): void
    {
        $owner = FlickrNsid::fake();
        $photosetId = fake()->uuid();
        $inSet = [(string) fake()->numerify('##########'), (string) fake()->numerify('##########')];
        $outside = (string) fake()->numerify('##########');

        foreach ([...$inSet, $outside] as $photoId) {
            Photo::query()->create([
                'owner_nsid' => $owner,
                'photo_id' => $photoId,
                'last_seen_at' => now(),
                'raw' => ['id' => $photoId, 'title' => fake()->sentence(2)],
            ]);
        }

        app(PhotoGroupRepository::class)->attachMany($owner, 'photoset', $photosetId, $inSet);

        $results = app(PhotoRepository::class)->inPhotoset($owner, $photosetId);

        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing($inSet, $results->pluck('photo_id')->all());
    }

    #[Test]
    public function favorited_by_loads_photos_from_favorite_pivot_ids(): void
    {
        $owner = FlickrNsid::fake();
        $favId = (string) fake()->numerify('##########');
        $otherId = (string) fake()->numerify('##########');

        foreach ([$favId, $otherId] as $photoId) {
            Photo::query()->create([
                'owner_nsid' => $owner,
                'photo_id' => $photoId,
                'last_seen_at' => now(),
                'raw' => ['id' => $photoId],
            ]);
        }

        app(PhotoFavoriteRepository::class)->markMany($owner, [$favId]);

        $results = app(PhotoRepository::class)->favoritedBy($owner);

        $this->assertCount(1, $results);
        $this->assertSame($favId, $results->first()->photo_id);
    }

    #[Test]
    public function empty_pivot_returns_empty_collection(): void
    {
        $owner = FlickrNsid::fake();
        Photo::query()->create([
            'owner_nsid' => $owner,
            'photo_id' => (string) fake()->numerify('##########'),
            'last_seen_at' => now(),
            'raw' => [],
        ]);

        $this->assertCount(0, app(PhotoRepository::class)->inGallery($owner, fake()->uuid()));
        $this->assertCount(0, app(PhotoRepository::class)->favoritedBy($owner));
    }
}
