<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Core;

/**
 * Shared redaction rules for telemetry/event payloads that may carry
 * credentials (headers, config arrays, query strings).
 */
final class SensitiveDataRedactor
{
    public const MASK = '[REDACTED]';

    private const SENSITIVE_KEYS = [
        'apikey', 'authorization', 'proxyauthorization', 'token',
        'accesstoken', 'refreshtoken', 'secret', 'password', 'cookie', 'setcookie',
    ];

    public static function isSensitiveKey(string $key): bool {
        $normalized = strtolower(str_replace(['-', '_'], '', $key));

        return match (true) {
            in_array($normalized, self::SENSITIVE_KEYS, true) => true,
            str_contains($normalized, 'apikey') => true,
            str_contains($normalized, 'authorization') => true,
            str_contains($normalized, 'cookie') => true,
            default => str_contains($normalized, 'token')
                || str_contains($normalized, 'secret')
                || str_contains($normalized, 'password'),
        };
    }

    /**
     * Recursively masks values under sensitive keys.
     *
     * @param array<array-key,mixed> $data
     * @return array<array-key,mixed>
     */
    public static function redactValues(array $data): array {
        $redacted = [];
        foreach ($data as $key => $value) {
            $redacted[$key] = match (true) {
                self::isSensitiveKey((string) $key) => self::MASK,
                is_array($value) => self::redactValues($value),
                default => $value,
            };
        }

        return $redacted;
    }
}
