<?php

namespace JeffersonGoncalves\PestPluginMysqlCompat\Functions;

class StringFunctions
{
    /**
     * @return array<string, array{0: callable, 1: int}>
     */
    public static function map(): array
    {
        return [
            'IF' => [self::ifFunction(...), 3],
            'CONCAT' => [self::concat(...), -1],
            'SUBSTRING' => [self::substring(...), -1],
            'LPAD' => [self::lpad(...), 3],
            'LEFT' => [self::left(...), 2],
            'RIGHT' => [self::right(...), 2],
            'SUBSTRING_INDEX' => [self::substringIndex(...), 3],
            'LENGTH' => [self::length(...), 1],
        ];
    }

    public static function ifFunction(mixed $condition, mixed $true, mixed $false): mixed
    {
        return $condition ? $true : $false;
    }

    public static function concat(mixed ...$parts): ?string
    {
        foreach ($parts as $part) {
            if ($part === null) {
                return null;
            }
        }

        return implode('', array_map(static fn (mixed $part): string => (string) $part, $parts));
    }

    /**
     * 1-indexed like MySQL. Negative $start counts from the end, matching mb_substr's own convention.
     */
    public static function substring(?string $string, int $start, ?int $length = null): ?string
    {
        if ($string === null) {
            return null;
        }

        if ($start === 0) {
            return '';
        }

        $offset = $start > 0 ? $start - 1 : $start;

        if ($length === null) {
            return mb_substr($string, $offset);
        }

        if ($length <= 0) {
            return '';
        }

        return mb_substr($string, $offset, $length);
    }

    /**
     * MySQL truncates instead of padding when $length <= strlen($string), and returns NULL
     * when padding is needed but $pad is empty.
     */
    public static function lpad(?string $string, int $length, ?string $pad): ?string
    {
        if ($string === null || $pad === null) {
            return null;
        }

        if ($length <= 0) {
            return '';
        }

        $currentLength = mb_strlen($string);

        if ($length <= $currentLength) {
            return mb_substr($string, 0, $length);
        }

        if ($pad === '') {
            return null;
        }

        $needed = $length - $currentLength;
        $padding = mb_substr(str_repeat($pad, (int) ceil($needed / mb_strlen($pad))), 0, $needed);

        return $padding.$string;
    }

    public static function left(?string $string, int $length): ?string
    {
        if ($string === null) {
            return null;
        }

        return mb_substr($string, 0, max($length, 0));
    }

    public static function right(?string $string, int $length): ?string
    {
        if ($string === null) {
            return null;
        }

        if ($length <= 0) {
            return '';
        }

        return mb_substr($string, -$length);
    }

    /**
     * Positive $count: everything left of the $count-th occurrence from the start.
     * Negative $count: everything right of the $count-th occurrence counted from the end.
     */
    public static function substringIndex(?string $string, ?string $delimiter, int $count): ?string
    {
        if ($string === null || $delimiter === null) {
            return null;
        }

        if ($delimiter === '' || $count === 0) {
            return '';
        }

        $parts = explode($delimiter, $string);

        if ($count > 0) {
            return implode($delimiter, array_slice($parts, 0, $count));
        }

        return implode($delimiter, array_slice($parts, $count));
    }

    /**
     * Overrides SQLite's native LENGTH (character count) with MySQL's byte count.
     */
    public static function length(?string $value): ?int
    {
        return $value === null ? null : strlen($value);
    }
}
