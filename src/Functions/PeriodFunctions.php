<?php

namespace JeffersonGoncalves\PestPluginMysqlCompat\Functions;

class PeriodFunctions
{
    /**
     * @return array<string, array{0: callable, 1: int}>
     */
    public static function map(): array
    {
        return [
            'PERIOD_ADD' => [self::periodAdd(...), 2],
        ];
    }

    /**
     * $period is YYYYMM. Returns the same type it received (int stays int, numeric string
     * stays string) — that is the measured, if unusual, MySQL behavior this package targets.
     */
    public static function periodAdd(int|string|null $period, ?int $months): int|string|null
    {
        if ($period === null || $period === '' || $months === null) {
            return null;
        }

        $value = (int) $period;
        $year = intdiv($value, 100);
        $month = $value % 100;

        if ($year < 100) {
            $year += $year < 70 ? 2000 : 1900;
        }

        $total = $year * 12 + ($month - 1) + $months;
        $newYear = intdiv($total, 12);
        $newMonth = $total % 12 + 1;

        $result = $newYear * 100 + $newMonth;

        return is_string($period) ? (string) $result : $result;
    }
}
