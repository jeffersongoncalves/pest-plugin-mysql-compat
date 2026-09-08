<?php

it('replicates MySQL PERIOD_ADD', function (string $expression, mixed $expected): void {
    expect(sqliteScalar($expression))->toBe($expected);
})->with([
    'PERIOD_ADD(202601, 2)' => ['PERIOD_ADD(202601, 2)', 202603],
    'PERIOD_ADD rolls over the year' => ['PERIOD_ADD(202611, 3)', 202702],
    'PERIOD_ADD with negative months' => ['PERIOD_ADD(202601, -2)', 202511],
    'PERIOD_ADD(NULL, months)' => ['PERIOD_ADD(NULL, 2)', null],
    'PERIOD_ADD(period, NULL)' => ['PERIOD_ADD(202601, NULL)', null],
    "PERIOD_ADD('', 2)" => ["PERIOD_ADD('', 2)", null],
]);

it('PERIOD_ADD preserves the received type', function (): void {
    expect(sqliteScalar("PERIOD_ADD('202601', 2)"))->toBe('202603');
});
