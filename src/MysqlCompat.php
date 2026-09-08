<?php

namespace JeffersonGoncalves\PestPluginMysqlCompat;

use Illuminate\Database\Connection;
use JeffersonGoncalves\PestPluginMysqlCompat\Laravel\CompatSqliteConnection;

/**
 * Public entry point. One line for the common case:
 *
 *   MysqlCompat::install();
 *
 * registers the UDF catalog against every sqlite connection the test suite opens from then on.
 */
class MysqlCompat
{
    public const REWRITE_ALL = 'all';

    public const REWRITE_NONE = 'none';

    private static bool $resolverRegistered = false;

    /**
     * @param  list<string>|null  $functions  Function names to install. Null (default) installs the whole catalog.
     * @param  string|list<string>  $rewriters  Accepted for forward-compatibility. Phase 2 adds the SQL/binding
     *                                          rewriters (Lexer, IsNullRewriter, IntervalRewriter,
     *                                          FloatBindingRewriter) — no-op for now.
     * @param  bool  $strict  Accepted for forward-compatibility. Phase 3 adds failing loud on unimplemented
     *                        MySQL functions — no-op for now.
     */
    public static function install(
        ?array $functions = null,
        string|array $rewriters = self::REWRITE_ALL,
        bool $strict = false,
    ): void {
        FunctionRegistry::$only = $functions;

        if (self::$resolverRegistered) {
            return;
        }

        self::$resolverRegistered = true;

        Connection::resolverFor(
            'sqlite',
            fn ($pdo, $database, $prefix, $config) => new CompatSqliteConnection($pdo, $database, $prefix, $config),
        );
    }

    /**
     * @return list<string>
     */
    public static function functions(): array
    {
        return array_keys(FunctionRegistry::all());
    }
}
