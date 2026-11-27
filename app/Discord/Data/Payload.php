<?php

declare(strict_types=1);

namespace App\Discord\Data;

/**
 * Safe, typed access into decoded Discord JSON (nested arrays of mixed).
 */
final class Payload
{
    /**
     * The value at a path, or null when any step is missing.
     *
     * @param  array<mixed>  $data
     */
    public static function get(array $data, string|int ...$path): mixed
    {
        $value = $data;

        foreach ($path as $key) {
            if (! is_array($value) || ! array_key_exists($key, $value)) {
                return null;
            }

            $value = $value[$key];
        }

        return $value;
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    public static function array(array $data, string|int ...$path): array
    {
        $value = self::get($data, ...$path);

        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<mixed>  $data
     */
    public static function string(array $data, string|int ...$path): ?string
    {
        $value = self::get($data, ...$path);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
