<?php

namespace JeffersonGoncalves\PestPluginMysqlCompat\Functions;

class NumericFunctions
{
    /**
     * @return array<string, array{0: callable, 1: int}>
     */
    public static function map(): array
    {
        return [
            'ROUND' => [self::round(...), 2],
        ];
    }

    /**
     * Overrides the 2-argument form only: SQLite's native ROUND ignores negative precision
     * (ROUND(1234.5, -2) => 1235.0), MySQL rounds to the nearest hundred (=> 1200). PHP's own
     * round() already matches MySQL here, so this is a thin type-coercing wrapper.
     */
    public static function round(int|float|string|null $value, int|float|string|null $precision): ?float
    {
        if ($value === null) {
            return null;
        }

        return round((float) $value, (int) ($precision ?? 0));
    }
}
