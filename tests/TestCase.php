<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * SAFETY: tests wipe and rebuild their database. This check runs as soon as the test
     * application boots — BEFORE any database work — and refuses everything except a
     * throwaway database, so tests can never touch Supabase or your real data.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $default = $app['config']->get('database.default');
        $driver = $app['config']->get("database.connections.$default.driver");
        $database = (string) $app['config']->get("database.connections.$default.database");

        $safe = ($driver === 'sqlite' && $database === ':memory:') || str_ends_with($database, '_test');

        if (! $safe) {
            throw new RuntimeException(
                "Refusing to run tests on database '$database'. Tests may only use SQLite :memory: "
                . "(the phpunit.xml default) or a database whose name ends in _test."
            );
        }

        return $app;
    }
}