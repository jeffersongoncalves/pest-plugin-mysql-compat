<?php

namespace JeffersonGoncalves\PestPluginMysqlCompat\Laravel;

use Illuminate\Database\Connection;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Optional: registers the sqlite connection resolver globally, for consumers who install
 * this as a normal Laravel package instead of calling MysqlCompat::install() by hand.
 * MysqlCompat::install() does the same registration itself, so most test suites never need
 * this provider at all.
 */
class CompatServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('pest-plugin-mysql-compat');
    }

    public function packageBooted(): void
    {
        Connection::resolverFor(
            'sqlite',
            fn ($pdo, $database, $prefix, $config) => new CompatSqliteConnection($pdo, $database, $prefix, $config),
        );
    }
}
