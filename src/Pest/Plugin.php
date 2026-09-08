<?php

namespace JeffersonGoncalves\PestPluginMysqlCompat\Pest;

use JeffersonGoncalves\PestPluginMysqlCompat\MysqlCompat;

/**
 * Pest activation helper. In tests/Pest.php:
 *
 *   \JeffersonGoncalves\PestPluginMysqlCompat\Pest\Plugin::boot();
 *
 * is shorthand for `pest()->beforeEach(fn () => MysqlCompat::install());`.
 *
 * Alternative (documented in the README): bind a TestCase whose own setUp() calls
 * MysqlCompat::install(), then `uses(TestCase::class)->in('Feature', 'Unit');` — the classic
 * Pest/PHPUnit pattern, useful when the suite already has a base TestCase to extend.
 */
class Plugin
{
    /**
     * @param  list<string>|null  $functions
     * @param  string|list<string>  $rewriters
     */
    public static function boot(
        ?array $functions = null,
        string|array $rewriters = MysqlCompat::REWRITE_ALL,
        bool $strict = false,
    ): void {
        pest()->beforeEach(fn () => MysqlCompat::install($functions, $rewriters, $strict));
    }
}
