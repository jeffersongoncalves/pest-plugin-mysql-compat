<?php

namespace JeffersonGoncalves\PestPluginMysqlCompat\Functions;

use DateTimeImmutable;

class DateFunctions
{
    /**
     * @return array<string, array{0: callable, 1: int}>
     */
    public static function map(): array
    {
        return [
            'DATE_FORMAT' => [self::dateFormat(...), 2],
            'LAST_DAY' => [self::lastDay(...), 1],
            'NOW' => [self::now(...), 0],
        ];
    }

    public static function dateFormat(?string $date, ?string $format): ?string
    {
        $parsed = self::parse($date);

        if ($parsed === null || $format === null) {
            return null;
        }

        return (string) preg_replace_callback('/%./', static function (array $matches) use ($parsed): string {
            return match ($matches[0]) {
                '%Y' => $parsed->format('Y'),
                '%y' => $parsed->format('y'),
                '%m' => $parsed->format('m'),
                '%c' => $parsed->format('n'),
                '%d' => $parsed->format('d'),
                '%e' => $parsed->format('j'),
                '%H' => $parsed->format('H'),
                '%k' => $parsed->format('G'),
                '%h', '%I' => $parsed->format('h'),
                '%i' => $parsed->format('i'),
                '%s', '%S' => $parsed->format('s'),
                '%p' => $parsed->format('A'),
                '%M' => $parsed->format('F'),
                '%b' => $parsed->format('M'),
                '%W' => $parsed->format('l'),
                '%a' => $parsed->format('D'),
                '%j' => sprintf('%03d', (int) $parsed->format('z') + 1),
                '%D' => $parsed->format('jS'),
                '%f' => $parsed->format('u'),
                '%%' => '%',
                default => $matches[0],
            };
        }, $format);
    }

    public static function lastDay(?string $date): ?string
    {
        return self::parse($date)?->modify('last day of this month')->format('Y-m-d');
    }

    public static function now(): string
    {
        return (new DateTimeImmutable)->format('Y-m-d H:i:s');
    }

    /**
     * Treats '', NULL and the legacy MySQL zero-date ('0000-00-00...') as NULL, instead of
     * letting strtotime() turn them into a plausible-looking wrong date.
     */
    public static function parse(?string $date): ?DateTimeImmutable
    {
        if ($date === null || $date === '' || str_starts_with($date, '0000-00-00')) {
            return null;
        }

        $timestamp = strtotime($date);

        if ($timestamp === false) {
            return null;
        }

        return (new DateTimeImmutable)->setTimestamp($timestamp);
    }
}
