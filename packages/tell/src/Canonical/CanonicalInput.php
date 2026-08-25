<?php

declare(strict_types=1);

namespace Cognesy\Tell\Canonical;

final class CanonicalInput
{
    /** @param array<string, mixed> $data @param list<string> $required @param list<string> $optional */
    public static function assertKeys(array $data, array $required, array $optional = []): void
    {
        $allowed = array_flip([...$required, ...$optional]);
        foreach ($data as $key => $_) {
            if (! is_string($key) || ! isset($allowed[$key])) {
                throw new CanonicalValidationException('Unsupported canonical field: '.(string) $key);
            }
        }
        foreach ($required as $key) {
            if (! array_key_exists($key, $data)) {
                throw new CanonicalValidationException("Missing required canonical field: {$key}");
            }
        }
    }

    public static function identifier(mixed $value, string $label): string
    {
        if (! is_string($value) || preg_match('/\A[A-Za-z][A-Za-z0-9._:-]{0,127}\z/', $value) !== 1) {
            throw new CanonicalValidationException("Canonical {$label} must be a compact stable identifier.");
        }

        return $value;
    }

    public static function string(mixed $value, string $label): string
    {
        if (! is_string($value) || preg_match('//u', $value) !== 1) {
            throw new CanonicalValidationException("Canonical {$label} must be valid UTF-8 text.");
        }

        return $value;
    }

    /** @return list<mixed> */
    public static function list(mixed $value, string $label): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new CanonicalValidationException("Canonical {$label} must be a list.");
        }

        return $value;
    }

    /** @return array<string, mixed> */
    public static function map(mixed $value, string $label): array
    {
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new CanonicalValidationException("Canonical {$label} must be an object.");
        }
        foreach (array_keys($value) as $key) {
            if (! is_string($key)) {
                throw new CanonicalValidationException("Canonical {$label} keys must be strings.");
            }
        }

        return $value;
    }

    public static function hash(mixed $value, string $label): CanonicalHash
    {
        if (! is_string($value)) {
            throw new CanonicalValidationException("Canonical {$label} must be a SHA-256 hash.");
        }

        return new CanonicalHash($value);
    }
}
