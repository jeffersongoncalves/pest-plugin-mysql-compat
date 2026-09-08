<?php

use Illuminate\Support\Facades\DB;
use JeffersonGoncalves\PestPluginMysqlCompat\MysqlCompat;

it('lists every UDF in the catalog', function (): void {
    expect(MysqlCompat::functions())->toEqualCanonicalizing([
        'IF', 'CONCAT', 'SUBSTRING', 'LPAD', 'LEFT', 'RIGHT', 'SUBSTRING_INDEX', 'LENGTH',
        'DATE_FORMAT', 'LAST_DAY', 'NOW', 'PERIOD_ADD', 'JSON_UNQUOTE', 'ROUND',
    ]);
});

it('installs idempotently on repeated getPdo() calls on the same connection', function (): void {
    MysqlCompat::install();

    // The connection resolved by TestCase::getEnvironmentSetUp() already went through
    // CompatSqliteConnection::getPdo() once; calling it again must not raise (sqlite would
    // throw were the same UDF name/argCount registered twice on a fresh, non-idempotent path).
    expect(DB::connection()->getPdo())->toBeInstanceOf(PDO::class);
    expect(sqliteScalar("CONCAT('a', 'b')"))->toBe('ab');
});

it('restricts installation to the requested functions', function (): void {
    MysqlCompat::install(functions: ['CONCAT']);

    expect(sqliteScalar("CONCAT('a', 'b')"))->toBe('ab');

    expect(fn () => sqliteScalar("LEFT('hello', 2)"))->toThrow(PDOException::class);

    // TestCase::setUp() calls MysqlCompat::install() with no filter before every test, so
    // this restriction never leaks into the rest of the suite.
});
