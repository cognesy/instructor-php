<?php declare(strict_types=1);

namespace Cognesy\Logging\Config;

use Cognesy\Config\BasePath;
use Cognesy\Config\Config;
use InvalidArgumentException;
use Psr\Log\LogLevel;
use Throwable;

final readonly class EventLogConfig
{
    public const CONFIG_GROUP = 'event_log';
    /** @var list<string> */
    private const DEFAULT_CONFIG_FILES = [
        'config/event_log.yaml',
        'packages/logging/resources/config/event_log.yaml',
        'vendor/cognesy/instructor-php/packages/logging/resources/config/event_log.yaml',
        'vendor/cognesy/instructor-logging/resources/config/event_log.yaml',
    ];

    public static function group(): string
    {
        return self::CONFIG_GROUP;
    }

    public function __construct(
        public string $path = '',
        public string $level = LogLevel::INFO,
        /** @var list<string> */
        public array $includeEvents = [],
        /** @var list<string> */
        public array $excludeEvents = [],
        public bool $useHierarchyFilter = true,
        public bool $excludeHttpDebug = false,
        public bool $includePayload = true,
        public bool $includeCorrelation = true,
        public bool $includeEventMetadata = true,
        public bool $includeComponentMetadata = true,
        public int $stringClipLength = 0,
    ) {}

    public static function default(?string $path = null): self
    {
        $defaults = self::fallbackDefaults();
        $fileConfig = $path === null
            ? self::loadDefaultConfigFile()
            : self::loadConfigFile($path);

        return self::fromArray(array_replace($defaults, $fileConfig, self::envOverrides()));
    }

    public static function fromFile(string $path): self
    {
        return self::fromArray(array_replace(self::fallbackDefaults(), self::loadConfigFile($path)));
    }

    /** @param array<string, mixed> $config */
    public static function fromArray(array $config): self
    {
        try {
            return new self(
                path: self::toString($config, 'path', ''),
                level: self::toString($config, 'level', LogLevel::INFO),
                includeEvents: self::toStringList($config, 'includeEvents'),
                excludeEvents: self::toStringList($config, 'excludeEvents'),
                useHierarchyFilter: self::toBool($config, 'useHierarchyFilter', true),
                excludeHttpDebug: self::toBool($config, 'excludeHttpDebug', false),
                includePayload: self::toBool($config, 'includePayload', true),
                includeCorrelation: self::toBool($config, 'includeCorrelation', true),
                includeEventMetadata: self::toBool($config, 'includeEventMetadata', true),
                includeComponentMetadata: self::toBool($config, 'includeComponentMetadata', true),
                stringClipLength: self::toInt($config, 'stringClipLength', 0),
            );
        } catch (Throwable $e) {
            $data = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            throw new InvalidArgumentException(
                message: "Invalid configuration for EventLogConfig: {$e->getMessage()}\nData: {$data}",
                previous: $e,
            );
        }
    }

    /** @param array<string, mixed> $overrides */
    public function withOverrides(array $overrides): self
    {
        return self::fromArray(array_replace($this->toArray(), $overrides));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'level' => $this->level,
            'includeEvents' => $this->includeEvents,
            'excludeEvents' => $this->excludeEvents,
            'useHierarchyFilter' => $this->useHierarchyFilter,
            'excludeHttpDebug' => $this->excludeHttpDebug,
            'includePayload' => $this->includePayload,
            'includeCorrelation' => $this->includeCorrelation,
            'includeEventMetadata' => $this->includeEventMetadata,
            'includeComponentMetadata' => $this->includeComponentMetadata,
            'stringClipLength' => $this->stringClipLength,
        ];
    }

    public function isEnabled(): bool
    {
        return trim($this->path) !== '';
    }

    /** @return array<string, mixed> */
    private static function fallbackDefaults(): array
    {
        return [
            'path' => '',
            'level' => LogLevel::INFO,
            'includeEvents' => [],
            'excludeEvents' => [],
            'useHierarchyFilter' => true,
            'excludeHttpDebug' => false,
            'includePayload' => true,
            'includeCorrelation' => true,
            'includeEventMetadata' => true,
            'includeComponentMetadata' => true,
            'stringClipLength' => 0,
        ];
    }

    /** @return array<string, mixed> */
    private static function loadDefaultConfigFile(): array
    {
        $paths = BasePath::resolveExisting(...self::DEFAULT_CONFIG_FILES);
        if ($paths === []) {
            return [];
        }

        return self::loadResolvedConfigFile($paths[0]);
    }

    /** @return array<string, mixed> */
    private static function loadConfigFile(string $path): array
    {
        $resolvedPath = BasePath::resolve($path);
        if (!is_file($resolvedPath)) {
            throw new InvalidArgumentException("Event log config file does not exist: {$resolvedPath}");
        }

        return self::loadResolvedConfigFile($resolvedPath);
    }

    /** @return array<string, mixed> */
    private static function loadResolvedConfigFile(string $path): array
    {
        $data = Config::fromPaths(dirname($path))
            ->load(basename($path))
            ->toArray();

        return is_array($data) ? $data : [];
    }

    /** @return array<string, mixed> */
    private static function envOverrides(): array
    {
        $overrides = [];
        $path = self::envValue('INSTRUCTOR_LOG_PATH');
        $level = self::envValue('INSTRUCTOR_LOG_LEVEL');

        if ($path !== null) {
            $overrides['path'] = $path;
        }

        if ($level !== null) {
            $overrides['level'] = $level;
        }

        return $overrides;
    }

    private static function envValue(string $name): ?string
    {
        if (array_key_exists($name, $_ENV)) {
            $value = $_ENV[$name];

            return match (true) {
                $value === null => null,
                is_scalar($value) => (string) $value,
                default => null,
            };
        }

        $value = getenv($name);
        return $value === false ? null : $value;
    }

    /** @param array<string, mixed> $config */
    private static function toString(array $config, string $key, string $default): string
    {
        if (!array_key_exists($key, $config)) {
            return $default;
        }

        $value = $config[$key];
        return match (true) {
            is_string($value) => $value,
            is_scalar($value) => (string) $value,
            default => throw new InvalidArgumentException(
                sprintf('Invalid %s value: expected string, got %s', $key, get_debug_type($value)),
            ),
        };
    }

    /** @param array<string, mixed> $config
     *  @return list<string>
     */
    private static function toStringList(array $config, string $key): array
    {
        if (!array_key_exists($key, $config)) {
            return [];
        }

        $value = $config[$key];
        if (!is_array($value)) {
            throw new InvalidArgumentException(
                sprintf('Invalid %s value: expected list, got %s', $key, get_debug_type($value)),
            );
        }

        $result = [];
        foreach ($value as $item) {
            $result[] = match (true) {
                is_string($item) => $item,
                is_scalar($item) => (string) $item,
                default => throw new InvalidArgumentException(
                    sprintf('Invalid %s item: expected string, got %s', $key, get_debug_type($item)),
                ),
            };
        }

        return array_values($result);
    }

    /** @param array<string, mixed> $config */
    private static function toBool(array $config, string $key, bool $default): bool
    {
        if (!array_key_exists($key, $config)) {
            return $default;
        }

        $value = $config[$key];
        if (is_bool($value)) {
            return $value;
        }

        if (!is_scalar($value)) {
            throw new InvalidArgumentException(
                sprintf('Invalid %s value: expected bool, got %s', $key, get_debug_type($value)),
            );
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($parsed !== null) {
            return $parsed;
        }

        throw new InvalidArgumentException(
            sprintf('Invalid %s value: expected bool, got %s', $key, get_debug_type($value)),
        );
    }

    /** @param array<string, mixed> $config */
    private static function toInt(array $config, string $key, int $default): int
    {
        if (!array_key_exists($key, $config)) {
            return $default;
        }

        $value = $config[$key];
        return match (true) {
            is_int($value) => $value,
            is_string($value) && preg_match('/^-?\d+$/', $value) === 1 => (int) $value,
            default => throw new InvalidArgumentException(
                sprintf('Invalid %s value: expected integer, got %s', $key, get_debug_type($value)),
            ),
        };
    }
}
