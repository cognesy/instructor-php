<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Common\Value;

final class Normalize
{
    public static function toString(mixed $value, string $default = ''): string
    {
        return match (true) {
            is_string($value) => $value,
            is_int($value), is_float($value) => (string) $value,
            is_bool($value) => $value ? 'true' : 'false',
            is_null($value) => $default,
            is_array($value), is_object($value) => self::encode($value, $default),
            default => $default,
        };
    }

    public static function toNullableString(mixed $value): ?string
    {
        return is_null($value) ? null : self::toString($value, '');
    }

    public static function toBool(mixed $value, bool $default = false): bool
    {
        return match (true) {
            is_bool($value) => $value,
            is_int($value) => $value !== 0,
            is_float($value) => $value != 0.0,
            is_string($value) => self::toBoolFromString($value, $default),
            default => $default,
        };
    }

    public static function toInt(mixed $value, int $default = 0): int
    {
        return match (true) {
            is_int($value) => $value,
            is_float($value) => (int) $value,
            is_bool($value) => $value ? 1 : 0,
            is_string($value) => is_numeric($value) ? (int) $value : $default,
            default => $default,
        };
    }

    public static function toNullableInt(mixed $value): ?int
    {
        return is_null($value) ? null : self::toInt($value);
    }

    public static function toFloat(mixed $value, float $default = 0.0): float
    {
        return match (true) {
            is_float($value), is_int($value) => (float) $value,
            is_bool($value) => $value ? 1.0 : 0.0,
            is_string($value) => is_numeric($value) ? (float) $value : $default,
            default => $default,
        };
    }

    /**
     * @return array<mixed>
     */
    public static function toArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private static function encode(mixed $value, string $default): string
    {
        $encoded = json_encode($value);
        return is_string($encoded) ? $encoded : $default;
    }

    private static function toBoolFromString(string $value, bool $default): bool
    {
        return match (strtolower(trim($value))) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => $default,
        };
    }
}
