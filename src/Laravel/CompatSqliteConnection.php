<?php

namespace JeffersonGoncalves\PestPluginMysqlCompat\Laravel;

use Illuminate\Database\SQLiteConnection;
use JeffersonGoncalves\PestPluginMysqlCompat\FunctionRegistry;
use PDO;
use WeakMap;

/**
 * The Laravel PDO is resolved lazily; installing the UDFs at connection construction time
 * would force it open early. getPdo() is the right hook, with per-PDO-instance idempotency
 * via WeakMap so re-registration never leaks across tests that build fresh connections.
 */
class CompatSqliteConnection extends SQLiteConnection
{
    /** @var WeakMap<PDO, true>|null */
    private static ?WeakMap $prepared = null;

    public function getPdo(): PDO
    {
        $pdo = parent::getPdo();

        self::$prepared ??= new WeakMap;

        if (! isset(self::$prepared[$pdo])) {
            self::$prepared[$pdo] = true;

            FunctionRegistry::install($pdo);
        }

        return $pdo;
    }
}
