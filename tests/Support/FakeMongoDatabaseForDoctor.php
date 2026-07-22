<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Support;

final class FakeMongoDatabaseForDoctor
{
    /**
     * @param  array<string, mixed>  $cmd
     */
    public function command(array $cmd): object
    {
        return (object) ['ok' => 1];
    }
}
