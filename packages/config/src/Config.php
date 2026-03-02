<?php declare(strict_types=1);

namespace Cognesy\Config;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

final class Config
{
    private const CACHE_VERSION = 1;

    public function __construct(
        private readonly string $path,
        private readonly ?string $cachePath = null,
    ) {}

    public function withCache(string $cachePath): self {
        return new self(path: $this->path, cachePath: $cachePath);
    }

    public function load(): ConfigEntry {
        $sourcePath = self::normalizePath($this->path);
        $rawData = match (true) {
            $this->cachePath === null => self::readSource($sourcePath),
            default => $this->readSourceWithCache($sourcePath),
        };
        $data = self::interpolateTemplates($rawData);

        return new ConfigEntry(
            key: ConfigKey::fromPath($sourcePath),
            sourcePath: $sourcePath,
            data: $data,
        );
    }

    private function readSourceWithCache(string $sourcePath): array {
        $sourceMtime = self::sourceMtime($sourcePath);
        $cachedPayload = $this->readCachePayload();

        if ($this->isSingleCacheFresh($cachedPayload, $sourcePath, $sourceMtime)) {
            return $cachedPayload['data'];
        }

        $data = self::readSource($sourcePath);
        $payload = [
            '_meta' => [
                'version' => self::CACHE_VERSION,
                'source' => $sourcePath,
                'mtime' => $sourceMtime,
            ],
            'data' => $data,
        ];

        $this->writeCachePayload($payload);
        return $data;
    }

    private function readCachePayload(): ?array {
        if ($this->cachePath === null || !is_file($this->cachePath)) {
            return null;
        }

        /** @var mixed $payload */
        $payload = require $this->cachePath;
        if (!is_array($payload)) {
            return null;
        }

        return $payload;
    }

    private function isSingleCacheFresh(?array $payload, string $sourcePath, int $sourceMtime): bool {
        if ($payload === null) {
            return false;
        }

        $meta = $payload['_meta'] ?? null;
        if (!is_array($meta)) {
            return false;
        }

        return match (true) {
            ($meta['version'] ?? null) !== self::CACHE_VERSION => false,
            ($meta['source'] ?? null) !== $sourcePath => false,
            ($meta['mtime'] ?? null) !== $sourceMtime => false,
            !isset($payload['data']) || !is_array($payload['data']) => false,
            default => true,
        };
    }

    private function writeCachePayload(array $payload): void {
        if ($this->cachePath === null) {
            return;
        }

        $cacheDirectory = dirname($this->cachePath);
        if (!is_dir($cacheDirectory)) {
            mkdir($cacheDirectory, 0777, true);
        }

        $export = var_export($payload, true);
        $content = "<?php declare(strict_types=1);\n\nreturn {$export};\n";
        file_put_contents($this->cachePath, $content);
    }

    private static function readSource(string $sourcePath): array {
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        $data = match ($extension) {
            'yaml', 'yml' => Yaml::parseFile($sourcePath),
            'php' => require $sourcePath,
            default => throw new InvalidArgumentException("Unsupported config extension: {$extension} in {$sourcePath}"),
        };

        if (is_array($data)) {
            return $data;
        }

        throw new InvalidArgumentException("Config file must return array-shaped data: {$sourcePath}");
    }

    private static function sourceMtime(string $sourcePath): int {
        if (!is_file($sourcePath)) {
            throw new InvalidArgumentException("Config file does not exist: {$sourcePath}");
        }

        $mtime = filemtime($sourcePath);
        if ($mtime === false) {
            throw new InvalidArgumentException("Cannot read modification time for config file: {$sourcePath}");
        }

        return $mtime;
    }

    private static function normalizePath(string $path): string {
        $real = realpath($path);
        return match (true) {
            is_string($real) && $real !== '' => $real,
            default => $path,
        };
    }

    private static function interpolateTemplates(array $data): array {
        $resolved = [];
        foreach ($data as $key => $value) {
            $resolved[$key] = self::interpolateValue($value);
        }
        return $resolved;
    }

    private static function interpolateValue(mixed $value): mixed {
        return match (true) {
            is_array($value) => self::interpolateTemplates($value),
            is_string($value) => self::interpolateString($value),
            default => $value,
        };
    }

    private static function interpolateString(string $value): string {
        if (!str_contains($value, '${')) {
            return $value;
        }

        $pattern = '/\$\{([A-Za-z_][A-Za-z0-9_]*)(?:(:-|\:\?|[?])([^}]*)?)?\}/';
        $resolved = preg_replace_callback(
            $pattern,
            static function (array $matches): string {
                $name = $matches[1];
                $operator = $matches[2] ?? '';
                $operand = $matches[3] ?? '';
                $env = self::readEnv($name);

                return match ($operator) {
                    '' => $env ?? '',
                    ':-' => ($env === null || $env === '') ? $operand : $env,
                    '?' => self::requiredValue($name, $env, null),
                    ':?' => self::requiredValue($name, $env, $operand),
                    default => throw new RuntimeException("Unsupported template operator '{$operator}' for {$name}"),
                };
            },
            $value,
        );

        if (!is_string($resolved)) {
            throw new RuntimeException('Template interpolation failed for non-string value');
        }

        return $resolved;
    }

    private static function requiredValue(string $name, ?string $env, ?string $customMessage): string {
        if ($env !== null && $env !== '') {
            return $env;
        }

        $message = match (true) {
            is_string($customMessage) && $customMessage !== '' => $customMessage,
            default => "Required environment variable '{$name}' is missing or empty",
        };
        throw new InvalidArgumentException($message);
    }

    private static function readEnv(string $name): ?string {
        $value = getenv($name);
        if (is_string($value)) {
            return $value;
        }

        $fromEnv = $_ENV[$name] ?? null;
        return match (true) {
            is_string($fromEnv) => $fromEnv,
            default => null,
        };
    }
}
