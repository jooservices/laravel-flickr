<?php

declare(strict_types=1);

namespace Jooservices\LaravelFlickr\Tests\Unit\Support;

use JOOservices\Flickr\DTO\Common\ApiErrorData;
use JOOservices\Flickr\DTO\Common\ApiResponseData;
use Jooservices\LaravelFlickr\Support\PagedResultFactory;
use Jooservices\LaravelFlickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class PagedResultFactoryTest extends TestCase
{
    #[Test]
    public function it_maps_contact_list_envelope(): void
    {
        $response = new ApiResponseData(true, [
            'contacts' => [
                'page' => 1,
                'pages' => 2,
                'perpage' => 2,
                'total' => 3,
                'contact' => [
                    ['nsid' => '1@N01', 'username' => 'a'],
                    ['nsid' => '2@N02', 'username' => 'b'],
                ],
            ],
        ]);

        $page = PagedResultFactory::fromApiResponse($response, 'contacts', ['contact']);

        $this->assertTrue($page->ok);
        $this->assertSame(1, $page->page);
        $this->assertSame(2, $page->pages);
        $this->assertSame(2, $page->perPage);
        $this->assertSame(3, $page->total);
        $this->assertCount(2, $page->items);
        $this->assertTrue($page->hasMorePages());
    }

    #[Test]
    public function it_maps_single_contact_object_as_one_item(): void
    {
        $response = new ApiResponseData(true, [
            'contacts' => [
                'page' => 1,
                'pages' => 1,
                'perpage' => 1,
                'total' => 1,
                'contact' => ['nsid' => '1@N01', 'username' => 'solo'],
            ],
        ]);

        $page = PagedResultFactory::fromApiResponse($response, 'contacts', ['contact']);

        $this->assertCount(1, $page->items);
        $this->assertSame('solo', $page->items[0]['username']);
        $this->assertFalse($page->hasMorePages());
    }

    #[Test]
    public function it_maps_api_failures(): void
    {
        $response = new ApiResponseData(false, [], new ApiErrorData(98, 'Invalid auth token'));
        $page = PagedResultFactory::fromApiResponse($response, 'contacts', ['contact']);

        $this->assertFalse($page->ok);
        $this->assertSame(98, $page->errorCode);
        $this->assertSame('Invalid auth token', $page->errorMessage);
        $this->assertSame([], $page->items);
    }
}
