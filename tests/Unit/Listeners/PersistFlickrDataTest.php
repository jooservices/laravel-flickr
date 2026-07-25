<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Listeners;

use Illuminate\Support\Facades\Event;
use JOOservices\LaravelFlickr\Adapters\Contacts;
use JOOservices\LaravelFlickr\Listeners\PersistFlickrData;
use JOOservices\LaravelFlickr\Models\Contact;
use JOOservices\LaravelFlickr\Tests\Support\FlickrNsid;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class PersistFlickrDataTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresMongoDb();
        $this->clearFlickrCollections();
    }

    #[Test]
    public function it_persists_contacts_from_a_completed_get_list_call(): void
    {
        Event::fake();
        $token = $this->storeToken();
        $contactNsid = FlickrNsid::fake();
        $username = fake()->userName();

        $event = $this->flickrCallCompleted(
            Contacts::NAMESPACE,
            Contacts::METHOD_GET_LIST,
            $token->userNsid,
            itemCount: 1,
            durationMs: 12.5,
            responseData: [
                'contacts' => [
                    'contact' => [
                        ['nsid' => $contactNsid, 'username' => $username],
                    ],
                ],
            ],
        );

        app(PersistFlickrData::class)->handle($event);

        $row = Contact::query()
            ->where('owner_nsid', $token->userNsid)
            ->where('contact_nsid', $contactNsid)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame($username, $row->raw['username'] ?? null);
        $this->assertNull($row->removed_at);
    }

    #[Test]
    public function it_skips_adapters_that_do_not_persist(): void
    {
        $this->storeApp();
        $before = Contact::query()->count();

        app(PersistFlickrData::class)->handle($this->flickrCallCompleted(
            'test',
            'login',
            responseData: ['user' => ['id' => FlickrNsid::fake()]],
        ));
        app(PersistFlickrData::class)->handle($this->flickrCallCompleted(
            'photos',
            'getInfo',
            FlickrNsid::fake(),
        ));

        $this->assertSame($before, Contact::query()->count());
    }

    #[Test]
    public function it_no_ops_unknown_namespaces_so_escape_hatch_calls_cannot_fail_after_success(): void
    {
        $before = Contact::query()->count();
        app(PersistFlickrData::class)->handle($this->flickrCallCompleted('groups', 'getInfo', FlickrNsid::fake()));
        $this->assertSame($before, Contact::query()->count());
    }

    #[Test]
    public function it_no_ops_when_container_misbinds_are_not_adapters(): void
    {
        $this->storeApp();
        $this->app->bind(\JOOservices\LaravelFlickr\Adapters\Test::class, static fn (): object => new \stdClass());

        $before = Contact::query()->count();
        app(PersistFlickrData::class)->handle($this->flickrCallCompleted('test', 'login', null));
        $this->assertSame($before, Contact::query()->count());
    }
}
