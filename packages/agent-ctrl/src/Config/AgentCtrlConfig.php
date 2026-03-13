<?php

declare(strict_types=1);

namespace Cognesy\AgentCtrl\Config;

use Cognesy\Config\Dsn;
use Cognesy\Sandbox\Enums\SandboxDriver;
use InvalidArgumentException;
use Throwable;

/**
 * Typed configuration for CLI-based agent builders.
 */
final readonly class AgentCtrlConfig
{
    public const CONFIG_GROUP = 'agents';

    public function __construct(
        public ?string $model = null,
        public ?int $timeout = null,
        public ?string $workingDirectory = null,
        public ?SandboxDriver $sandboxDriver = null,
    ) {}

    public static function group(): string
    {
        return self::CONFIG_GROUP;
    }

    public static function fromArray(array $config): self
    {
        $normalized = self::normalizeArray($config);

        try {
            return new self(...$normalized);
        } catch (Throwable $e) {
            $data = json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            throw new InvalidArgumentException(
                message: "Failed to create AgentCtrlConfig from array:\n{$data}\nError: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    public static function fromDsn(string $dsn): self
    {
        return self::fromArray(Dsn::fromString($dsn)->toArray());
    }

    public function withOverrides(array $values): self
    {
        $overrides = self::nonNullValues(self::normalizeArray($values));

        return self::fromArray(array_merge($this->toArray(), $overrides));
    }

    public function toArray(): array
    {
        return [
            'model' => $this->model,
            'timeout' => $this->timeout,
            'workingDirectory' => $this->workingDirectory,
            'sandboxDriver' => $this->sandboxDriver?->value,
        ];
    }

    private static function normalizeArray(array $config): array
    {
        $normalized = $config;

        if (array_key_exists('directory', $normalized) && !array_key_exists('workingDirectory', $normalized)) {
            $normalized['workingDirectory'] = $normalized['directory'];
        }

        if (array_key_exists('sandbox', $normalized) && !array_key_exists('sandboxDriver', $normalized)) {
            $normalized['sandboxDriver'] = $normalized['sandbox'];
        }

        $normalized = array_intersect_key($normalized, [
            'model' => true,
            'timeout' => true,
            'workingDirectory' => true,
            'sandboxDriver' => true,
        ]);

        if (array_key_exists('model', $normalized)) {
            $normalized['model'] = self::toNullableString('model', $normalized['model']);
        }

        if (array_key_exists('timeout', $normalized)) {
            $normalized['timeout'] = self::toNullablePositiveInt('timeout', $normalized['timeout']);
        }

        if (array_key_exists('workingDirectory', $normalized)) {
            $normalized['workingDirectory'] = self::toNullableString('workingDirectory', $normalized['workingDirectory']);
        }

        if (array_key_exists('sandboxDriver', $normalized)) {
            $normalized['sandboxDriver'] = self::toNullableSandboxDriver($normalized['sandboxDriver']);
        }

        return $normalized;
    }

    private static function nonNullValues(array $values): array
    {
        return array_filter(
            $values,
            static fn(mixed $value): bool => $value !== null,
        );
    }

    private static function toNullableString(string $field, mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_string($value) && trim($value) === '' => null,
            is_string($value) => $value,
            is_scalar($value) => (string) $value,
            default => throw new InvalidArgumentException(
                sprintf('Invalid %s value: expected string, got %s', $field, get_debug_type($value)),
            ),
        };
    }

    private static function toNullablePositiveInt(string $field, mixed $value): ?int
    {
        return match (true) {
            $value === null => null,
            is_string($value) && trim($value) === '' => null,
            is_int($value) && $value > 0 => $value,
            is_int($value) => null,
            is_string($value) && preg_match('/^-?\d+$/', $value) === 1 && (int) $value > 0 => (int) $value,
            is_string($value) && preg_match('/^-?\d+$/', $value) === 1 => null,
            default => throw new InvalidArgumentException(
                sprintf('Invalid %s value: expected integer, got %s', $field, get_debug_type($value)),
            ),
        };
    }

    private static function toNullableSandboxDriver(mixed $value): ?SandboxDriver
    {
        return match (true) {
            $value === null => null,
            $value instanceof SandboxDriver => $value,
            is_string($value) && trim($value) === '' => null,
            is_string($value) => SandboxDriver::from($value),
            default => throw new InvalidArgumentException(
                sprintf('Invalid sandboxDriver value: expected string, got %s', get_debug_type($value)),
            ),
        };
    }
}
