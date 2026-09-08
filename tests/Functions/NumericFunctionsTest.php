<?php

it('replicates MySQL ROUND with negative precision', function (string $expression, mixed $expected): void {
    expect(sqliteScalar($expression))->toBe($expected);
})->with([
    'ROUND(1234.5, -2)' => ['ROUND(1234.5, -2)', 1200.0],
    'ROUND(1234.5, -3)' => ['ROUND(1234.5, -3)', 1000.0],
    'ROUND with positive precision still works' => ['ROUND(1.005, 2)', 1.01],
    'ROUND(NULL, precision)' => ['ROUND(NULL, -2)', null],
]);
