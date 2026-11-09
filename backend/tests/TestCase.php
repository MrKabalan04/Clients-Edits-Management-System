<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'mysql') {
            $this->assertSame(
                'client_edits_test',
                config('database.connections.mysql.database'),
                'Automated tests must run against the dedicated client_edits_test database.'
            );
        }
    }
}
