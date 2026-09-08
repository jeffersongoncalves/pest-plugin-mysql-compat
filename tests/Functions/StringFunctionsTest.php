<?php

it('replicates MySQL string functions', function (string $expression, mixed $expected): void {
    expect(sqliteScalar($expression))->toBe($expected);
})->with([
    // IF
    "IF(1, 'yes', 'no')" => ["IF(1, 'yes', 'no')", 'yes'],
    "IF(0, 'yes', 'no')" => ["IF(0, 'yes', 'no')", 'no'],
    'IF(NULL, ...)' => ["IF(NULL, 'yes', 'no')", 'no'],

    // CONCAT — NULL propagation in every argument position
    "CONCAT('a', 'b')" => ["CONCAT('a', 'b')", 'ab'],
    'CONCAT(NULL, first)' => ["CONCAT(NULL, 'b')", null],
    'CONCAT(NULL, last)' => ["CONCAT('a', NULL)", null],
    'CONCAT(NULL, middle)' => ["CONCAT('a', NULL, 'c')", null],
    "CONCAT('', 'b')" => ["CONCAT('', 'b')", 'b'],
    'CONCAT accented' => ["CONCAT('a', 'ção')", 'ação'],

    // SUBSTRING — 1-indexed, negative start from the end
    "SUBSTRING('hello', 2)" => ["SUBSTRING('hello', 2)", 'ello'],
    "SUBSTRING('hello', 2, 2)" => ["SUBSTRING('hello', 2, 2)", 'el'],
    "SUBSTRING('hello', -3, 2)" => ["SUBSTRING('hello', -3, 2)", 'll'],
    'SUBSTRING(NULL, ...)' => ['SUBSTRING(NULL, 1)', null],
    "SUBSTRING('', 1)" => ["SUBSTRING('', 1)", ''],

    // LPAD
    "LPAD('hi', 5, 'ab')" => ["LPAD('hi', 5, 'ab')", 'abahi'],
    'LPAD truncates when len <= strlen' => ["LPAD('hello', 3, 'x')", 'hel'],
    'LPAD(NULL, ...)' => ["LPAD(NULL, 5, 'x')", null],

    // LEFT / RIGHT
    "LEFT('hello', 2)" => ["LEFT('hello', 2)", 'he'],
    'LEFT(NULL, ...)' => ['LEFT(NULL, 2)', null],
    "RIGHT('hello', 2)" => ["RIGHT('hello', 2)", 'lo'],
    'RIGHT(NULL, ...)' => ['RIGHT(NULL, 2)', null],

    // SUBSTRING_INDEX — negative count counts from the end
    "SUBSTRING_INDEX('a.b.c', '.', 2)" => ["SUBSTRING_INDEX('a.b.c', '.', 2)", 'a.b'],
    "SUBSTRING_INDEX('a.b.c', '.', -2)" => ["SUBSTRING_INDEX('a.b.c', '.', -2)", 'b.c'],
    'SUBSTRING_INDEX(NULL, ...)' => ["SUBSTRING_INDEX(NULL, '.', 1)", null],

    // LENGTH — byte count, not character count
    "LENGTH('ção')" => ["LENGTH('ção')", 5],
    "LENGTH('')" => ["LENGTH('')", 0],
    'LENGTH(NULL)' => ['LENGTH(NULL)', null],
]);
