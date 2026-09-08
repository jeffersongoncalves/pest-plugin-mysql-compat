<?php

namespace JeffersonGoncalves\PestPluginMysqlCompat\Functions;

class JsonFunctions
{
    /**
     * @return array<string, array{0: callable, 1: int}>
     */
    public static function map(): array
    {
        return [
            'JSON_UNQUOTE' => [self::unquote(...), 1],
        ];
    }

    /**
     * Not a blind identity: strips the surrounding double quotes MySQL's JSON_EXTRACT adds,
     * but leaves the value untouched when SQLite's native json_extract already returned the
     * bare scalar. Both formats must work — see spec §5.3.
     */
    public static function unquote(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        if (mb_strlen($value) < 2 || ! str_starts_with($value, '"') || ! str_ends_with($value, '"')) {
            return $value;
        }

        $decoded = json_decode($value);

        return is_string($decoded) ? $decoded : $value;
    }
}
