<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Tests\Support;

use Illuminate\Database\Eloquent\Model;

final class SqliteMemoryModel extends Model
{
    protected $table = 'x';

    protected $connection = 'sqlite_mem';
}
