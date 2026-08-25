<?php

declare(strict_types=1);

namespace Cognesy\Tell\Observability;

use BackedEnum;
use DateTimeInterface;
use JsonSerializable;
use Stringable;
use UnitEnum;

final class TracePayload
{
    private const int MAX_DEPTH = 8;

    private const array PAYLOAD_KEYS = [
        'args',
        'context',
        'input',
        'messagepayload',
        'output',
        'prompt',
        'result',
        'state',
        'stopcontext',
    ];

    private const array SECRET_KEYS = [
        'accesstoken',
        'apikey',
        'authorization',
        'cookie',
        'credential',
        'dsn',
        'password',
        'privatekey',
        'proxyauthorization',
        'refreshtoken',
        'setcookie',
        'secret',
    ];

    public static function sanitize(mixed $value, bool $includePayloads, int $maxStringLength): mixed
    {
        return self::value($value, $includePayloads, $maxStringLength, 0);
    }

    private static function value(
        mixed $value,
        bool $includePayloads,
        int $maxStringLength,
        int $depth,
    ): mixed {
        return match (true) {
            $depth >= self::MAX_DEPTH => '[depth-limit]',
            is_string($value) => self::string($value, $maxStringLength),
            is_array($value) => self::array($value, $includePayloads, $maxStringLength, $depth),
            $value instanceof DateTimeInterface => $value->format(DateTimeInterface::ATOM),
            $value instanceof UnitEnum => $value instanceof BackedEnum ? $value->value : $value->name,
            $value instanceof JsonSerializable => self::value(
                $value->jsonSerialize(),
                $includePayloads,
                $maxStringLength,
                $depth + 1,
            ),
            $value instanceof Stringable => self::string((string) $value, $maxStringLength),
            is_object($value) => self::value(
                get_object_vars($value),
                $includePayloads,
                $maxStringLength,
                $depth + 1,
            ),
            is_resource($value) => '[resource]',
            default => $value,
        };
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private static function array(
        array $values,
        bool $includePayloads,
        int $maxStringLength,
        int $depth,
    ): array {
        $sanitized = [];
        foreach ($values as $key => $value) {
            $normalized = is_string($key) ? self::normalizedKey($key) : '';
            $sanitized[$key] = match (true) {
                self::isSecretKey($normalized) => '[redacted]',
                ! $includePayloads && in_array($normalized, self::PAYLOAD_KEYS, true) => '[omitted]',
                default => self::value($value, $includePayloads, $maxStringLength, $depth + 1),
            };
        }

        return $sanitized;
    }

    private static function string(string $value, int $maxStringLength): string
    {
        if (mb_strlen($value) <= $maxStringLength) {
            return $value;
        }

        return mb_substr($value, 0, $maxStringLength).'...';
    }

    private static function normalizedKey(string $key): string
    {
        return strtolower(str_replace(['-', '_', '.'], '', $key));
    }

    private static function isSecretKey(string $key): bool
    {
        return match (true) {
            in_array($key, self::SECRET_KEYS, true) => true,
            str_contains($key, 'password') => true,
            str_contains($key, 'secret') => true,
            str_ends_with($key, 'apikey') => true,
            str_ends_with($key, 'accesstoken') => true,
            str_ends_with($key, 'refreshtoken') => true,
            default => false,
        };
    }

    private function __construct() {}
}
