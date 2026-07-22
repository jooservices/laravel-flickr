<?php

declare(strict_types=1);

namespace JOOservices\LaravelFlickr\Support;

use JOOservices\LaravelConfig\Facades\Config;

/**
 * Shared casting helpers for flat laravel-config reads.
 */
final class LaravelConfigValue
{
    public static function string(string $path, string $default): string
    {
        $value = Config::get($path, $default);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    public static function nullableString(string $path): ?string
    {
        $value = Config::get($path);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public static function int(string $path, int $default): int
    {
        $value = Config::get($path, $default);

        if (is_int($value)) {
            return $value;
        }

        return is_numeric($value) ? (int) $value : $default;
    }

    public static function bool(string $path, bool $default): bool
    {
        $value = Config::get($path, $default);

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_string($value)) {
            $filtered = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

            return $filtered ?? $default;
        }

        return $default;
    }
}
