<?php

namespace JeffersonGoncalves\PestPluginMysqlCompat\Tests;

use JeffersonGoncalves\PestPluginMysqlCompat\PestPluginMysqlCompatServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            PestPluginMysqlCompatServiceProvider::class,
        ];
    }
}
