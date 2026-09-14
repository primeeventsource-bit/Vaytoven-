<?php

namespace Tests;

use App\Support\Storage\FilePresence;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // FilePresence caches "is this file on the disk" for the life of a
        // request. A test run is one process, so without this a fake disk from
        // an earlier test would answer for a later one.
        FilePresence::forget();
    }
}
