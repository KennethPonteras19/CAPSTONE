<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();

        if ($app['config']['database.default'] !== 'sqlite'
            || $app['config']['database.connections.sqlite.database'] !== ':memory:') {
            throw new \RuntimeException('Tests must use the isolated SQLite :memory: database.');
        }

        return $app;
    }
}
