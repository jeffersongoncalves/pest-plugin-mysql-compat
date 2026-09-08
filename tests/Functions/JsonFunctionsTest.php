<?php

it('replicates MySQL JSON_UNQUOTE', function (string $expression, mixed $expected): void {
    expect(sqliteScalar($expression))->toBe($expected);
})->with([
    // MySQL-shaped input: JSON_EXTRACT-style value wrapped in double quotes.
    'unquotes a quoted string' => ['JSON_UNQUOTE(\'"x"\')', 'x'],
    // SQLite's native json_extract already returns the bare scalar — must pass through untouched.
    "json_extract('{\"a\":\"x\"}', '\$.a')" => ["JSON_UNQUOTE(json_extract('{\"a\":\"x\"}', '\$.a'))", 'x'],
    'passes through a bare integer' => ["JSON_UNQUOTE(json_extract('{\"a\":7}', '\$.a'))", 7],
    'passes through NULL' => ['JSON_UNQUOTE(NULL)', null],
    'passes through empty string' => ["JSON_UNQUOTE('')", ''],
    'passes through accented text unquoted' => ["JSON_UNQUOTE('ção')", 'ção'],
    'unquotes accented text' => ['JSON_UNQUOTE(\'"ção"\')', 'ção'],
]);
