<div class="filament-hidden">

<!-- banner: art/jeffersongoncalves-pest-plugin-mysql-compat.png (generate via portfolio-banner skill) -->

</div>

# Pest Plugin MySQL Compat

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jeffersongoncalves/pest-plugin-mysql-compat.svg?style=flat-square)](https://packagist.org/packages/jeffersongoncalves/pest-plugin-mysql-compat)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/jeffersongoncalves/pest-plugin-mysql-compat/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/jeffersongoncalves/pest-plugin-mysql-compat/actions?query=workflow%3ATests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/jeffersongoncalves/pest-plugin-mysql-compat/pint.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/jeffersongoncalves/pest-plugin-mysql-compat/actions?query=workflow%3A%22Fix+PHP+code+styling%22+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/jeffersongoncalves/pest-plugin-mysql-compat.svg?style=flat-square)](https://packagist.org/packages/jeffersongoncalves/pest-plugin-mysql-compat)
[![License](https://img.shields.io/packagist/l/jeffersongoncalves/pest-plugin-mysql-compat.svg?style=flat-square)](LICENSE.md)

Run a MySQL-flavored Laravel test suite against SQLite in memory without changing a single
line of production SQL. **The production query is the truth; the test database is what
adapts.**

A Laravel project with a legacy MySQL database usually runs its test suite on SQLite for
speed and isolation. Every query that uses a MySQL-only function then dies in the test, and
the usual fixes are all bad: rewriting the query to a SQLite-compatible subset (now the test
covers SQL that isn't the production SQL), marking the test `->todo()` (the business rule
goes uncovered), or requiring a real MySQL for the suite (losing isolation and speed). This
package installs the missing/divergent MySQL functions as SQLite user-defined functions
(UDFs), with fidelity to `NULL` propagation, byte-length semantics, and legacy zero-dates —
so the query itself never has to change.

## Installation

You can install the package via composer:

```bash
composer require jeffersongoncalves/pest-plugin-mysql-compat --dev
```

## Usage

### Pest

```php
// tests/Pest.php
use JeffersonGoncalves\PestPluginMysqlCompat\Pest\Plugin;

Plugin::boot();
```

This is shorthand for:

```php
// tests/Pest.php
use JeffersonGoncalves\PestPluginMysqlCompat\MysqlCompat;

pest()->beforeEach(fn () => MysqlCompat::install());
```

**Alternative**, without the Pest-specific helper — bind your own base `TestCase` instead:

```php
// tests/Pest.php
uses(Tests\TestCase::class)->in('Feature', 'Unit');
```

```php
// tests/TestCase.php
use JeffersonGoncalves\PestPluginMysqlCompat\MysqlCompat;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        MysqlCompat::install();

        parent::setUp();
    }
}
```

### PHPUnit / plain Laravel

```php
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        MysqlCompat::install();

        parent::setUp();
    }
}
```

`MysqlCompat::install()` must run **before** the app resolves its first database connection —
it registers a connection resolver, and resolvers only affect connections created afterwards.
That is why it is called before `parent::setUp()`, not after.

### Granular configuration

```php
MysqlCompat::install(
    functions: ['DATE_FORMAT', 'IF', 'CONCAT'],   // default: all
    rewriters: MysqlCompat::REWRITE_ALL,           // reserved for Phase 2, no-op today
    strict: true,                                  // reserved for Phase 3, no-op today
);
```

## Function catalog

### Missing from SQLite — installed as UDFs

| Function | Signature | Fidelity note |
|--------|------------|--------------------------|
| `IF` | 3 args | SQLite has native `IIF`; registering `IF` works |
| `JSON_UNQUOTE` | 1 arg | see below — not a blind identity |
| `CONCAT` | variadic | **propagates `NULL`** |
| `SUBSTRING` | 2–3 args | 1-indexed, like MySQL |
| `DATE_FORMAT` | 2 args | treats `'0000-00-00'` as `NULL` |
| `LPAD` | 3 args | |
| `LEFT` | 2 args | |
| `RIGHT` | 2 args | |
| `SUBSTRING_INDEX` | 3 args | negative count counts from the end |
| `PERIOD_ADD` | 2 args | returns the same type it received |
| `LAST_DAY` | 1 arg | |
| `NOW` | 0 args | |

### Native in SQLite, but with divergent semantics — overridden

| Function | SQLite native | MySQL | Action |
|--------|--------|-------|--------|
| `LENGTH` | `LENGTH('ção')` → **3** (characters) | → **5** (bytes) | overridden with `strlen` |
| `ROUND` | `ROUND(1234.5, -2)` → **1235.0** | → **1200** | overridden for negative precision |

`LENGTH` is the most treacherous divergence: on a column of digits (CPF, CNPJ, CEP) both
agree and the test passes; on accented text they silently diverge.

`JSON_EXTRACT` was measured to diverge too (`JSON_EXTRACT` returns a bare scalar in SQLite,
a quoted JSON value in MySQL) but is **not** overridden — `JSON_UNQUOTE` is adapted instead
to strip the surrounding quotes only when they are present, so it works over either shape.

## Known limitations

- **`WHERE` of `UPDATE`/`DELETE` with a float parameter** is not rewritten — the (Phase 2)
  float-binding rewriter only targets `select()`/`cursor()`. No real case found yet;
  documented instead of covered speculatively.
- **Rewriting will be textual** (Phase 2). SQL assembled at runtime by string concatenation
  with a fragment coming from data is risky territory; the lexer will refuse when unsure,
  and refusing means the original error comes back, not corrupted SQL.
- **`GROUP_CONCAT`** exists in both engines with the same default separator (`,`), but
  ordering and `DISTINCT` diverge. It belongs in a future catalog addition with a parity
  test, not as "already works".
- **Text collation/ordering** is not covered. `ORDER BY` on accented text differs between
  the two engines — that is a collation problem, not a function problem.
- **The `LENGTH` override** changes behavior for anyone who was already writing SQL expecting
  SQLite's native character-count `LENGTH`. This is a breaking change for that consumer.

## Roadmap

- [x] **Phase 1 — what pays for the package.** `FunctionRegistry` + the 12 missing functions
      + `LENGTH` and `ROUND` overrides. Pest and PHPUnit integration. Unit tests.
- [ ] **Phase 2 — the differentiator.** `Lexer` + `FloatBindingRewriter` (highest measured
      payoff) + `IsNullRewriter` + `IntervalRewriter`.
- [ ] **Phase 3 — confidence.** `scan` diagnostic command, `strict` mode, a MySQL-parity
      suite that runs against a real MySQL service in CI.
- [ ] **Phase 4 — reach.** The demand-driven catalog (`IFNULL`, `CONCAT_WS`, `RPAD`,
      `DATEDIFF`, `DAY`/`MONTH`/`YEAR`, `ADDDATE`, `PERIOD_DIFF`, `STR_TO_DATE`,
      `TIMESTAMPDIFF`, `GROUP_CONCAT`, `FIND_IN_SET`, `FIELD`, `GREATEST`, `LEAST`, …) and an
      adapter for consumers not on Laravel (Doctrine, plain PDO).

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email the author instead of using the
issue tracker.

## Credits

- [Jefferson Gonçalves](https://github.com/jeffersongoncalves)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
