<?php

namespace JeffersonGoncalves\PestPluginMysqlCompat;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class PestPluginMysqlCompatServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('pest-plugin-mysql-compat')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigrations();
    }
}
