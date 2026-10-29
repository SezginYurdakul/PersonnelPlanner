<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuses to run against any database whose name doesn't end in "_test": feature tests
     * wipe their database (RefreshDatabase), and a cached config or a missing phpunit env
     * would otherwise point them at the development data.
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $database = (string) $app['config']->get('database.connections.'.$app['config']->get('database.default').'.database');

        if (! str_ends_with($database, '_test')) {
            throw new RuntimeException("Tests refuse to run against database \"{$database}\" - its name must end in _test.");
        }

        return $app;
    }
}
