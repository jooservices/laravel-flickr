<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Repositories;

use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use JOOservices\LaravelFlickr\Events\FlickrContactRemoved;
use JOOservices\LaravelFlickr\Events\FlickrPhotoGroupRemoved;
use JOOservices\LaravelFlickr\Events\FlickrPhotoRemoved;
use JOOservices\LaravelFlickr\Events\FlickrPhotoUnfavorited;
use JOOservices\LaravelFlickr\Models\Contact;
use JOOservices\LaravelFlickr\Models\Photo;
use JOOservices\LaravelFlickr\Models\PhotoFavorite;
use JOOservices\LaravelFlickr\Models\PhotoGroup;
use JOOservices\LaravelFlickr\Repositories\ContactRepository;
use JOOservices\LaravelFlickr\Repositories\PhotoFavoriteRepository;
use JOOservices\LaravelFlickr\Repositories\PhotoGroupRepository;
use JOOservices\LaravelFlickr\Repositories\PhotoRepository;
use JOOservices\LaravelFlickr\Service\PersistenceReconcileService;
use JOOservices\LaravelFlickr\Tests\Support\FlickrNsid;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class RepositoryReconcileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresMongoDb();
        $this->clearFlickrCollections();
    }

    #[Test]
    public function contact_reconcile_soft_removes_stale_rows(): void
    {
        Event::fake([FlickrContactRemoved::class]);
        $owner = FlickrNsid::fake();
        $stale = FlickrNsid::fake();
        $fresh = FlickrNsid::fake();
        $cutoff = Carbon::now()->subMinute();

        Contact::query()->create([
            'owner_nsid' => $owner,
            'contact_nsid' => $stale,
            'last_seen_at' => $cutoff->copy()->subMinute(),
            'removed_at' => null,
            'raw' => ['nsid' => $stale],
        ]);
        Contact::query()->create([
            'owner_nsid' => $owner,
            'contact_nsid' => $fresh,
            'last_seen_at' => Carbon::now(),
            'removed_at' => null,
            'raw' => ['nsid' => $fresh],
        ]);

        $removed = app(PersistenceReconcileService::class)->reconcileContacts($owner, $cutoff);

        $this->assertSame(1, $removed);
        $this->assertNotNull(Contact::query()->where('contact_nsid', $stale)->first()?->removed_at);
        $this->assertNull(Contact::query()->where('contact_nsid', $fresh)->first()?->removed_at);
        Event::assertDispatched(FlickrContactRemoved::class);
    }

    #[Test]
    public function photo_reconcile_soft_removes_stale_rows(): void
    {
        Event::fake([FlickrPhotoRemoved::class]);
        $owner = FlickrNsid::fake();
        $staleId = (string) fake()->numerify('##########');
        $cutoff = Carbon::now()->subMinute();

        Photo::query()->create([
            'owner_nsid' => $owner,
            'photo_id' => $staleId,
            'last_seen_at' => $cutoff->copy()->subMinute(),
            'removed_at' => null,
            'raw' => ['id' => $staleId],
        ]);

        $removed = app(PersistenceReconcileService::class)->reconcilePhotos($owner, $cutoff);

        $this->assertSame(1, $removed);
        Event::assertDispatched(FlickrPhotoRemoved::class);
    }

    #[Test]
    public function photo_group_reconcile_soft_removes_stale_membership(): void
    {
        Event::fake([FlickrPhotoGroupRemoved::class]);
        $owner = FlickrNsid::fake();
        $groupId = fake()->uuid();
        $photoId = (string) fake()->numerify('##########');
        $cutoff = Carbon::now()->subMinute();

        PhotoGroup::query()->create([
            'owner_nsid' => $owner,
            'photo_id' => $photoId,
            'group_type' => 'photoset',
            'group_id' => $groupId,
            'last_seen_at' => $cutoff->copy()->subMinute(),
            'removed_at' => null,
        ]);

        $removed = app(PersistenceReconcileService::class)->reconcilePhotoGroup($owner, 'photoset', $groupId, $cutoff);

        $this->assertSame(1, $removed);
        Event::assertDispatched(FlickrPhotoGroupRemoved::class);
    }

    #[Test]
    public function favorite_reconcile_soft_removes_stale_favorites(): void
    {
        Event::fake([FlickrPhotoUnfavorited::class]);
        $owner = FlickrNsid::fake();
        $photoId = (string) fake()->numerify('##########');
        $cutoff = Carbon::now()->subMinute();

        PhotoFavorite::query()->create([
            'owner_nsid' => $owner,
            'photo_id' => $photoId,
            'last_seen_at' => $cutoff->copy()->subMinute(),
            'removed_at' => null,
        ]);

        $removed = app(PersistenceReconcileService::class)->reconcileFavorites($owner, $cutoff);

        $this->assertSame(1, $removed);
        Event::assertDispatched(FlickrPhotoUnfavorited::class);
    }

    #[Test]
    public function contact_and_photo_upserts_skip_items_without_identity(): void
    {
        $owner = FlickrNsid::fake();
        app(ContactRepository::class)->upsertMany($owner, [['username' => 'no-nsid'], ['nsid' => '1@N01']]);
        app(PhotoRepository::class)->upsertMany($owner, [['title' => 'no-id'], ['id' => '99']]);

        $this->assertSame(1, Contact::query()->where('owner_nsid', $owner)->count());
        $this->assertTrue(Contact::query()->where('owner_nsid', $owner)->where('contact_nsid', '1@N01')->exists());
        $this->assertSame(1, Photo::query()->where('owner_nsid', $owner)->count());
        $this->assertTrue(Photo::query()->where('owner_nsid', $owner)->where('photo_id', '99')->exists());
    }

    #[Test]
    public function attach_and_mark_many_skip_empty_photo_ids(): void
    {
        $owner = FlickrNsid::fake();
        app(PhotoGroupRepository::class)->attachMany($owner, 'photoset', 'set-1', ['', '11']);
        app(PhotoFavoriteRepository::class)->markMany($owner, ['', '22']);

        $this->assertSame(['11'], app(PhotoGroupRepository::class)->photoIdsIn($owner, 'photoset', 'set-1'));
        $this->assertSame(['22'], app(PhotoFavoriteRepository::class)->photoIdsForOwner($owner));
    }
}
