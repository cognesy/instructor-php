<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Models;

use InvalidArgumentException;

/** Shared structural validation for serialized model facts. */
final class ModelRecordFields
{
    /** @param list<string> $allowed @param list<string> $nullable */
    public static function validate(array $data, array $allowed, string $label, array $nullable = []): void {
        foreach ($data as $key => $value) {
            if (!in_array($key, $allowed, true)) {
                throw new InvalidArgumentException("Unexpected {$label} field: {$key}");
            }
            if ($value === null && !in_array($key, $nullable, true)) {
                throw new InvalidArgumentException("{$label}.{$key} must not be null.");
            }
        }
    }

    public static function object(array $data, string $key, string $label): array {
        $value = $data[$key] ?? [];
        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new InvalidArgumentException("{$label}.{$key} must be an object.");
        }

        return $value;
    }

    public static function schemaVersion(mixed $version): void {
        if ($version !== 1) {
            throw new InvalidArgumentException('Unsupported model record schemaVersion; expected integer 1.');
        }
    }
}
