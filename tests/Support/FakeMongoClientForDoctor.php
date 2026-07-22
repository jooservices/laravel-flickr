<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Support;

final class FakeMongoClientForDoctor
{
    public function selectDatabase(string $name): FakeMongoDatabaseForDoctor
    {
        return new FakeMongoDatabaseForDoctor();
    }
}
