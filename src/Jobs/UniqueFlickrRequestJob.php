<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;

/**
 * Opt-in unique wrapper for hosts that need 60s dedupe of identical Flickr calls.
 * Default {@see FlickrRequestJob} is not unique — crawl retries are not silently dropped.
 */
final class UniqueFlickrRequestJob extends FlickrRequestJob implements ShouldBeUnique
{
    public int $uniqueFor = 60;
}
