<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Adapters;

use Illuminate\Support\Facades\Event;
use JOOservices\LaravelFlickr\Tests\Support\FlickrNsid;
use JOOservices\LaravelFlickr\Tests\Support\InteractsWithFlickrAdapters;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class TagsAdapterTest extends TestCase
{
    use InteractsWithFlickrAdapters;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requiresMongoDb();
        $this->clearFlickrCollections();
    }

    #[Test]
    public function get_list_user_calls_flickr_tags_get_list_user(): void
    {
        Event::fake();
        $token = $this->storeToken();
        $userId = $token->userNsid;

        $this->assertAdapterCall(
            fn () => $this->flickrAs($token)->tags->getListUser($userId),
            'flickr.tags.getListUser',
            [
                'who' => [
                    'tags' => [
                        'tag' => [['_content' => 'sunset']],
                    ],
                ],
            ],
            ['user_id' => $userId],
        );
    }

    #[Test]
    public function get_list_user_popular_maps_user_id(): void
    {
        Event::fake();
        $token = $this->storeToken();
        $userId = $token->userNsid;

        $this->assertAdapterCall(
            fn () => $this->flickrAs($token)->tags->getListUserPopular($userId, ['count' => 10]),
            'flickr.tags.getListUserPopular',
            [
                'who' => [
                    'tags' => [
                        'tag' => [['_content' => 'popular']],
                    ],
                ],
            ],
            ['user_id' => $userId, 'count' => '10'],
        );
    }

    #[Test]
    public function get_list_user_raw_maps_user_id(): void
    {
        Event::fake();
        $token = $this->storeToken();
        $userId = FlickrNsid::fake();

        $this->assertAdapterCall(
            fn () => $this->flickrAs($token)->tags->getListUserRaw($userId),
            'flickr.tags.getListUserRaw',
            [
                'who' => [
                    'tags' => [
                        'tag' => [['_content' => 'raw-tag']],
                    ],
                ],
            ],
            ['user_id' => $userId],
        );
    }

    #[Test]
    public function get_hot_list_calls_flickr_tags_get_hot_list(): void
    {
        Event::fake();
        $token = $this->storeToken();

        $this->assertAdapterCall(
            fn () => $this->flickrAs($token)->tags->getHotList(['period' => 'week', 'count' => 5]),
            'flickr.tags.getHotList',
            [
                'hottags' => [
                    'tag' => [['_content' => 'hot']],
                ],
            ],
            ['period' => 'week', 'count' => '5'],
        );
    }

    #[Test]
    public function get_list_photo_maps_photo_id(): void
    {
        Event::fake();
        $token = $this->storeToken();
        $photoId = $this->fakePhotoId();

        $this->assertAdapterCall(
            fn () => $this->flickrAs($token)->tags->getListPhoto($photoId),
            'flickr.tags.getListPhoto',
            [
                'photo' => [
                    'tags' => [
                        'tag' => [['_content' => 'on-photo']],
                    ],
                ],
            ],
            ['photo_id' => $photoId],
        );
    }

    #[Test]
    public function get_related_maps_tag(): void
    {
        Event::fake();
        $token = $this->storeToken();

        $this->assertAdapterCall(
            fn () => $this->flickrAs($token)->tags->getRelated('sunset'),
            'flickr.tags.getRelated',
            [
                'tags' => [
                    'tag' => [['_content' => 'sunrise']],
                ],
            ],
            ['tag' => 'sunset'],
        );
    }
}
