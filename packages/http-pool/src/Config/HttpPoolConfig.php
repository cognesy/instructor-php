<?php declare(strict_types=1);

namespace Cognesy\HttpPool\Config;

use Cognesy\Config\Dsn;
use InvalidArgumentException;

final class HttpPoolConfig
{
    public const CONFIG_GROUP = 'pool';
    /** @var list<string> */
    private const STRING_FIELDS = ['driver'];
    /** @var list<string> */
    private const INT_FIELDS = [
        'connectTimeout',
        'requestTimeout',
        'idleTimeout',
        'streamChunkSize',
        'maxConcurrent',
        'poolTimeout',
    ];
    /** @var list<string> */
    private const BOOL_FIELDS = ['failOnError'];

    public function __construct(
        public readonly string $driver = 'curl',
        public readonly int $connectTimeout = 3,
        public readonly int $requestTimeout = 30,
        public readonly int $idleTimeout = -1,
        public readonly int $streamChunkSize = 256,
        public readonly int $maxConcurrent = 5,
        public readonly int $poolTimeout = 120,
        public readonly bool $failOnError = false,
    ) {}

    public static function group(): string
    {
        return self::CONFIG_GROUP;
    }

    public static function fromDsn(string $dsn): self
    {
        return self::fromArray(self::coerceDsnTypes(Dsn::fromString($dsn)->toArray()));
    }

    public static function fromArray(array $config): self
    {
        try {
            return new self(...$config);
        } catch (\Throwable $e) {
            $data = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            throw new InvalidArgumentException(
                message: "Failed to create HttpPoolConfig from array:\n$data\nError: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    public function withOverrides(array $overrides): self
    {
        return self::fromArray(array_merge($this->toArray(), $overrides));
    }

    public function toArray(): array
    {
        return [
            'driver' => $this->driver,
            'connectTimeout' => $this->connectTimeout,
            'requestTimeout' => $this->requestTimeout,
            'idleTimeout' => $this->idleTimeout,
            'streamChunkSize' => $this->streamChunkSize,
            'maxConcurrent' => $this->maxConcurrent,
            'poolTimeout' => $this->poolTimeout,
            'failOnError' => $this->failOnError,
        ];
    }

    /** @param array<string, mixed> $data */
    private static function coerceDsnTypes(array $data): array
    {
        $typed = [];

        foreach ($data as $key => $value) {
            $typed[$key] = self::coerceDsnValue($key, $value);
        }

        return $typed;
    }

    private static function coerceDsnValue(string $key, mixed $value): mixed
    {
        return match (true) {
            in_array($key, self::STRING_FIELDS, true) => self::coerceStringValue($key, $value),
            in_array($key, self::INT_FIELDS, true) => self::coerceIntValue($key, $value),
            in_array($key, self::BOOL_FIELDS, true) => self::coerceBoolValue($key, $value),
            default => $value,
        };
    }

    private static function coerceStringValue(string $key, mixed $value): string
    {
        return match (true) {
            is_string($value) => $value,
            is_scalar($value) => (string) $value,
            default => throw new InvalidArgumentException(
                message: sprintf('Invalid DSN value for "%s": expected string, got %s', $key, get_debug_type($value)),
            ),
        };
    }

    private static function coerceIntValue(string $key, mixed $value): int
    {
        return match (true) {
            is_int($value) => $value,
            is_string($value) && preg_match('/^-?\d+$/', $value) === 1 => (int) $value,
            default => throw new InvalidArgumentException(
                message: sprintf(
                    'Invalid DSN value for "%s": expected integer, got %s (%s)',
                    $key,
                    get_debug_type($value),
                    self::stringifyDsnValue($value),
                ),
            ),
        };
    }

    private static function coerceBoolValue(string $key, mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (!is_scalar($value)) {
            throw new InvalidArgumentException(
                message: sprintf('Invalid DSN value for "%s": expected boolean, got %s', $key, get_debug_type($value)),
            );
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($parsed !== null) {
            return $parsed;
        }

        throw new InvalidArgumentException(
            message: sprintf(
                'Invalid DSN value for "%s": expected boolean, got %s (%s)',
                $key,
                get_debug_type($value),
                self::stringifyDsnValue($value),
            ),
        );
    }

    private static function stringifyDsnValue(mixed $value): string
    {
        return match (true) {
            is_scalar($value) => (string) $value,
            default => json_encode($value, JSON_UNESCAPED_SLASHES) ?: get_debug_type($value),
        };
    }
}
