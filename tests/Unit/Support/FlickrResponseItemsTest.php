<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Unit\Support;

use JOOservices\LaravelFlickr\Support\FlickrResponseItems;
use JOOservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class FlickrResponseItemsTest extends TestCase
{
    #[Test]
    public function from_envelope_returns_list_items(): void
    {
        $items = FlickrResponseItems::fromEnvelope(
            [
                'photos' => [
                    'photo' => [
                        ['id' => '1', 'title' => 'a'],
                        ['id' => '2', 'title' => 'b'],
                    ],
                ],
            ],
            'photos',
            'photo',
        );

        $this->assertCount(2, $items);
        $this->assertSame('1', $items[0]['id']);
    }

    #[Test]
    public function from_envelope_wraps_single_associative_item(): void
    {
        $items = FlickrResponseItems::fromEnvelope(
            ['contacts' => ['contact' => ['nsid' => '1@N01', 'username' => 'bob']]],
            'contacts',
            'contact',
        );

        $this->assertCount(1, $items);
        $this->assertSame('1@N01', $items[0]['nsid']);
    }

    #[Test]
    public function from_envelope_returns_empty_for_missing_or_invalid_shapes(): void
    {
        $this->assertSame([], FlickrResponseItems::fromEnvelope([], 'photos', 'photo'));
        $this->assertSame([], FlickrResponseItems::fromEnvelope(['photos' => 'nope'], 'photos', 'photo'));
        $this->assertSame([], FlickrResponseItems::fromEnvelope(['photos' => ['photo' => 'x']], 'photos', 'photo'));
    }

    #[Test]
    public function ids_extracts_string_and_numeric_ids(): void
    {
        $ids = FlickrResponseItems::ids([
            ['id' => '10'],
            ['id' => 20],
            ['id' => ''],
            ['title' => 'no-id'],
            ['id' => null],
        ]);

        $this->assertSame(['10', '20'], $ids);
    }

    #[Test]
    public function from_envelope_skips_non_array_list_entries(): void
    {
        $items = FlickrResponseItems::fromEnvelope(
            ['photos' => ['photo' => [['id' => '1'], 'skip-me', ['id' => 2]]]],
            'photos',
            'photo',
        );

        $this->assertCount(2, $items);
        $this->assertSame(['1', '2'], FlickrResponseItems::ids($items));
    }
}
