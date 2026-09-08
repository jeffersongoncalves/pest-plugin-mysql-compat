<?php

namespace JeffersonGoncalves\PestPluginMysqlCompat\Tests;

use JeffersonGoncalves\PestPluginMysqlCompat\Laravel\CompatServiceProvider;
use JeffersonGoncalves\PestPluginMysqlCompat\MysqlCompat;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        // Must run before the app boots any database connection, since resolverFor() only
        // affects connections resolved after it was registered.
        MysqlCompat::install();

        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            CompatServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
