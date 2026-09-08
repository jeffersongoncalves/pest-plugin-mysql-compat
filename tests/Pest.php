<?php

use Illuminate\Support\Facades\DB;
use JeffersonGoncalves\PestPluginMysqlCompat\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit', 'Functions');

/**
 * Evaluates a scalar SQL expression against the in-memory sqlite connection and returns the
 * raw value. Used to assert parity with documented MySQL behavior, e.g.:
 *
 *   expect(sqliteScalar("CONCAT('a', NULL)"))->toBeNull();
 */
function sqliteScalar(string $expression): mixed
{
    return DB::selectOne("SELECT {$expression} AS value")->value;
}
