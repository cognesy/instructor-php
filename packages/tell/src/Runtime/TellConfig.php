<?php

declare(strict_types=1);

namespace Cognesy\Tell\Runtime;

use JsonException;
use RuntimeException;

final readonly class TellConfig
{
    private const string SCHEMA = 'tell.config.v1';

    public function __construct(
        public bool $executionTraces = true,
        public bool $includePayloads = false,
        public int $maxStringLength = 4096,
    ) {
        if ($this->maxStringLength < 256) {
            throw new RuntimeException('Tell observability.maxStringLength must be at least 256.');
        }
    }

    public static function fromFile(string $path): self
    {
        if (! is_file($path)) {
            return new self;
        }
        $contents = @file_get_contents($path);
        if (! is_string($contents)) {
            throw new RuntimeException("Failed to read Tell config: {$path}");
        }
        try {
            $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException("Invalid JSON in Tell config {$path}: {$error->getMessage()}", previous: $error);
        }
        if (! is_array($decoded)) {
            throw new RuntimeException("Tell config must contain a JSON object: {$path}");
        }
        self::assertKeys($decoded, ['schema', 'observability'], 'Tell config');
        $schema = $decoded['schema'] ?? self::SCHEMA;
        if ($schema !== self::SCHEMA) {
            throw new RuntimeException("Unsupported Tell config schema in {$path}: ".self::display($schema));
        }
        $observability = $decoded['observability'] ?? [];
        if (! is_array($observability)) {
            throw new RuntimeException("Tell config observability must be a JSON object: {$path}");
        }
        self::assertKeys(
            $observability,
            ['executionTraces', 'includePayloads', 'maxStringLength'],
            'Tell config observability',
        );

        return new self(
            executionTraces: self::boolean($observability, 'executionTraces', true),
            includePayloads: self::boolean($observability, 'includePayloads', false),
            maxStringLength: self::integer($observability, 'maxStringLength', 4096),
        );
    }

    /** @return array<string, bool|int> */
    public function toArray(): array
    {
        return [
            'executionTraces' => $this->executionTraces,
            'includePayloads' => $this->includePayloads,
            'maxStringLength' => $this->maxStringLength,
        ];
    }

    /** @param array<string, mixed> $values */
    private static function boolean(array $values, string $key, bool $default): bool
    {
        $value = $values[$key] ?? $default;
        if (! is_bool($value)) {
            throw new RuntimeException("Tell config observability.{$key} must be a boolean.");
        }

        return $value;
    }

    /** @param array<string, mixed> $values */
    private static function integer(array $values, string $key, int $default): int
    {
        $value = $values[$key] ?? $default;
        if (! is_int($value)) {
            throw new RuntimeException("Tell config observability.{$key} must be an integer.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $allowed
     */
    private static function assertKeys(array $values, array $allowed, string $location): void
    {
        $unknown = array_values(array_diff(array_keys($values), $allowed));
        if ($unknown !== []) {
            throw new RuntimeException($location.' contains unknown key(s): '.implode(', ', $unknown).'.');
        }
    }

    private static function display(mixed $value): string
    {
        return match (true) {
            is_scalar($value), $value === null => var_export($value, true),
            default => get_debug_type($value),
        };
    }
}
