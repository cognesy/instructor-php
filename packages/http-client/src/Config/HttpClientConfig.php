<?php declare(strict_types=1);

namespace Cognesy\Http\Config;

use Cognesy\Config\BasePath;
use Cognesy\Config\Config;
use Cognesy\Config\Dsn;
use InvalidArgumentException;

/**
 * Class HttpClientConfig
 *
 * Configuration class for HTTP clients.
 */
final class HttpClientConfig
{
    public const CONFIG_GROUP = 'http';
    /** @var list<string> */
    private const STRING_FIELDS = ['driver'];
    /** @var list<string> */
    private const INT_FIELDS = [
        'connectTimeout',
        'requestTimeout',
        'idleTimeout',
        'streamChunkSize',
        'streamHeaderTimeout',
    ];
    /** @var list<string> */
    private const BOOL_FIELDS = ['failOnError'];

    public static function group() : string {
        return self::CONFIG_GROUP;
    }

    private const PRESET_PATHS = [
        'config/http/presets',
        'packages/http-client/resources/config/http/presets',
        'vendor/cognesy/instructor-php/packages/http-client/resources/config/http/presets',
        'vendor/cognesy/instructor-http/resources/config/http/presets',
    ];

    public function __construct(
        public readonly string $driver = 'curl',
        public readonly int    $connectTimeout = 3,
        public readonly int    $requestTimeout = 30,
        public readonly int    $idleTimeout = -1,
        public readonly int    $streamChunkSize = 256,
        public readonly int    $streamHeaderTimeout = 5,
        public readonly bool   $failOnError = false,
    ) {}

    public static function fromPreset(string $preset, ?string $basePath = null): self {
        $basePaths = $basePath !== null ? [$basePath] : self::PRESET_PATHS;
        $resolvedPaths = BasePath::resolveExisting(...$basePaths);
        if ($resolvedPaths === []) {
            throw new InvalidArgumentException("No preset directory found for '{$preset}'. Searched: " . implode(', ', $basePaths));
        }
        $data = Config::fromPaths(...$resolvedPaths)
            ->load("{$preset}.yaml")
            ->toArray();
        return self::fromArray($data);
    }

    public static function fromDsn(string $dsn) : HttpClientConfig {
        $data = Dsn::fromString($dsn)->toArray();
        return self::fromArray(self::coerceDsnTypes($data));
    }

    /**
     * Creates a new HttpClientConfig instance from an associative array.
     *
     * @param array $config An associative array containing configuration options.
     * @return HttpClientConfig The corresponding HttpClientConfig instance based on the provided array.
     */
    public static function fromArray(array $config) : HttpClientConfig {
        try {
            $instance = new self(...$config);
        } catch (\Throwable $e) {
            $data = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            throw new InvalidArgumentException(
                message: "Failed to create HttpClientConfig from array:\n$data\nError: {$e->getMessage()}",
                previous: $e
            );
        }
        return $instance;
    }

    public function withOverrides(array $overrides) : self {
        $config = array_merge($this->toArray(), $overrides);
        return self::fromArray($config);
    }

    public function toArray() : array {
        return [
            'driver' => $this->driver,
            'connectTimeout' => $this->connectTimeout,
            'requestTimeout' => $this->requestTimeout,
            'idleTimeout' => $this->idleTimeout,
            'streamChunkSize' => $this->streamChunkSize,
            'streamHeaderTimeout' => $this->streamHeaderTimeout,
            'failOnError' => $this->failOnError,
        ];
    }

    /** @param array<string, mixed> $data */
    private static function coerceDsnTypes(array $data): array {
        $typed = [];
        foreach ($data as $key => $value) {
            $typed[$key] = self::coerceDsnValue($key, $value);
        }
        return $typed;
    }

    private static function coerceDsnValue(string $key, mixed $value): mixed {
        return match (true) {
            in_array($key, self::STRING_FIELDS, true) => self::coerceStringValue($key, $value),
            in_array($key, self::INT_FIELDS, true) => self::coerceIntValue($key, $value),
            in_array($key, self::BOOL_FIELDS, true) => self::coerceBoolValue($key, $value),
            default => $value,
        };
    }

    private static function coerceStringValue(string $key, mixed $value): string {
        return match (true) {
            is_string($value) => $value,
            is_scalar($value) => (string) $value,
            default => throw new InvalidArgumentException(
                message: sprintf('Invalid DSN value for "%s": expected string, got %s', $key, get_debug_type($value)),
            ),
        };
    }

    private static function coerceIntValue(string $key, mixed $value): int {
        return match (true) {
            is_int($value) => $value,
            is_string($value) && preg_match('/^-?\d+$/', $value) === 1 => (int) $value,
            default => throw new InvalidArgumentException(
                message: sprintf('Invalid DSN value for "%s": expected integer, got %s (%s)', $key, get_debug_type($value), self::stringifyDsnValue($value)),
            ),
        };
    }

    private static function coerceBoolValue(string $key, mixed $value): bool {
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
            message: sprintf('Invalid DSN value for "%s": expected boolean, got %s (%s)', $key, get_debug_type($value), self::stringifyDsnValue($value)),
        );
    }

    private static function stringifyDsnValue(mixed $value): string {
        return match (true) {
            is_scalar($value) => (string) $value,
            default => json_encode($value, JSON_UNESCAPED_SLASHES) ?: get_debug_type($value),
        };
    }
}
