<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Adapters;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use JOOservices\Flickr\DTO\Common\ApiResponseData;
use JOOservices\LaravelFlickr\Adapters\Contacts;
use JOOservices\LaravelFlickr\Jobs\FlickrRequestJob;
use JOOservices\LaravelFlickr\Listeners\PersistFlickrData;
use JOOservices\LaravelFlickr\Models\Contact;
use JOOservices\LaravelFlickr\Tests\Support\FlickrNsid;
use JOOservices\LaravelFlickr\Tests\Support\InteractsWithFlickrAdapters;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ContactsAdapterTest extends TestCase
{
    use InteractsWithFlickrAdapters;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresMongoDb();
        $this->clearFlickrCollections();
    }

    #[Test]
    public function get_list_calls_flickr_contacts_get_list(): void
    {
        Event::fake();
        $token = $this->storeToken();
        $contactNsid = FlickrNsid::fake();

        $response = $this->assertAdapterCall(
            fn () => $this->flickrAs($token)->contacts->getList(['per_page' => 25]),
            'flickr.contacts.getList',
            [
                'contacts' => [
                    'page' => 1,
                    'pages' => 1,
                    'perpage' => 25,
                    'total' => 1,
                    'contact' => [['nsid' => $contactNsid, 'username' => fake()->userName()]],
                ],
            ],
            ['per_page' => '25'],
        );

        $this->assertSame($contactNsid, $response->data['contacts']['contact'][0]['nsid'] ?? null);
    }

    #[Test]
    public function get_public_list_calls_flickr_contacts_get_public_list(): void
    {
        Event::fake();
        $userId = FlickrNsid::fake();

        $this->assertAdapterCall(
            fn () => $this->flickrAnonymous()->contacts->getPublicList($userId, ['per_page' => 10]),
            'flickr.contacts.getPublicList',
            [
                'contacts' => [
                    'page' => 1,
                    'pages' => 1,
                    'perpage' => 10,
                    'total' => 0,
                    'contact' => [],
                ],
            ],
            ['user_id' => $userId, 'per_page' => '10'],
        );
    }

    #[Test]
    public function get_list_persists_contacts_via_listener(): void
    {
        Event::fake();
        $token = $this->storeToken();
        $contactNsid = FlickrNsid::fake();
        $username = fake()->userName();

        $this->fakeFlickrResponses([[
            'contacts' => [
                'contact' => [['nsid' => $contactNsid, 'username' => $username]],
            ],
        ]]);

        $response = $this->flickrAs($token)->contacts->getList();
        $this->assertInstanceOf(ApiResponseData::class, $response);

        app(PersistFlickrData::class)->handle($this->flickrCallCompleted(
            Contacts::NAMESPACE,
            Contacts::METHOD_GET_LIST,
            $token->userNsid,
            itemCount: 1,
            response: $response,
        ));

        $row = Contact::query()
            ->where('owner_nsid', $token->userNsid)
            ->where('contact_nsid', $contactNsid)
            ->first();
        $this->assertNotNull($row);
        $this->assertSame($username, $row->raw['username'] ?? null);
    }

    #[Test]
    public function queued_get_list_dispatches_job_without_running_http(): void
    {
        Event::fake();
        Bus::fake();
        $token = $this->storeToken();

        $result = $this->flickrAs($token)->contacts->getList(queued: true);

        $this->assertNull($result);
        Bus::assertDispatched(FlickrRequestJob::class);
    }

    #[Test]
    public function persist_skips_failed_and_public_list_methods(): void
    {
        $nsid = FlickrNsid::fake();
        $adapter = app(Contacts::class, ['appName' => 'default', 'nsid' => $nsid]);
        $adapter->persist($this->flickrCallCompleted(Contacts::NAMESPACE, Contacts::METHOD_GET_LIST, $nsid, ok: false));
        $adapter->persist($this->flickrCallCompleted(Contacts::NAMESPACE, Contacts::METHOD_GET_PUBLIC_LIST, $nsid));
        $this->assertSame(0, Contact::query()->where('owner_nsid', $nsid)->count());
    }
}
