<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\ClaudeCode\Domain\Dto\StreamEvent;

final class StreamValueNormalizer
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

    /**
     * @return array<string, mixed>
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
