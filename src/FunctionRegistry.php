<?php

namespace JeffersonGoncalves\PestPluginMysqlCompat;

use JeffersonGoncalves\PestPluginMysqlCompat\Functions\DateFunctions;
use JeffersonGoncalves\PestPluginMysqlCompat\Functions\JsonFunctions;
use JeffersonGoncalves\PestPluginMysqlCompat\Functions\NumericFunctions;
use JeffersonGoncalves\PestPluginMysqlCompat\Functions\PeriodFunctions;
use JeffersonGoncalves\PestPluginMysqlCompat\Functions\StringFunctions;
use PDO;

/**
 * Installs the UDF catalog into a PDO instance. Called from CompatSqliteConnection::getPdo(),
 * which already guarantees idempotency per PDO instance via a WeakMap — this class stays a
 * dumb installer and does not need to track what it already registered.
 */
class FunctionRegistry
{
    /**
     * Function names to install. Null installs the whole catalog.
     *
     * @var list<string>|null
     */
    public static ?array $only = null;

    public static function install(PDO $pdo): void
    {
        $functions = self::all();

        if (self::$only !== null) {
            $functions = array_intersect_key($functions, array_flip(self::$only));
        }

        foreach ($functions as $name => [$callback, $argCount]) {
            $pdo->sqliteCreateFunction($name, $callback, $argCount);
        }
    }

    /**
     * @return array<string, array{0: callable, 1: int}>
     */
    public static function all(): array
    {
        return [
            ...StringFunctions::map(),
            ...DateFunctions::map(),
            ...PeriodFunctions::map(),
            ...JsonFunctions::map(),
            ...NumericFunctions::map(),
        ];
    }
}
