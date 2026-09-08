<?php

it('replicates MySQL date functions', function (string $expression, mixed $expected): void {
    expect(sqliteScalar($expression))->toBe($expected);
})->with([
    "DATE_FORMAT('2026-05-01', '%d/%m/%Y')" => ["DATE_FORMAT('2026-05-01', '%d/%m/%Y')", '01/05/2026'],
    "DATE_FORMAT('2026-05-01 13:05:09', '%H:%i:%s')" => ["DATE_FORMAT('2026-05-01 13:05:09', '%H:%i:%s')", '13:05:09'],
    'DATE_FORMAT(NULL, format)' => ["DATE_FORMAT(NULL, '%d/%m/%Y')", null],
    'DATE_FORMAT(date, NULL)' => ["DATE_FORMAT('2026-05-01', NULL)", null],
    "DATE_FORMAT('', ...)" => ["DATE_FORMAT('', '%d/%m/%Y')", null],
    'DATE_FORMAT legacy zero date' => ["DATE_FORMAT('0000-00-00', '%d/%m/%Y')", null],
    'DATE_FORMAT legacy zero datetime' => ["DATE_FORMAT('0000-00-00 00:00:00', '%d/%m/%Y')", null],

    "LAST_DAY('2026-02-10')" => ["LAST_DAY('2026-02-10')", '2026-02-28'],
    'LAST_DAY(NULL)' => ['LAST_DAY(NULL)', null],
    "LAST_DAY('')" => ["LAST_DAY('')", null],
    'LAST_DAY legacy zero date' => ["LAST_DAY('0000-00-00')", null],
]);

it('NOW returns the current datetime', function (): void {
    expect(sqliteScalar('NOW()'))->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/');
});
