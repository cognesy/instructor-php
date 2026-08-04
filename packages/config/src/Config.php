<?php

declare(strict_types=1);

namespace Cognesy\Config;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;
use Throwable;

final class Config
{
    private const CACHE_VERSION = 2;

    private readonly EnvTemplate $template;

    /** @var array<int, string> */
    private readonly array $paths;

    /**
     * @param  array<string>  $paths
     */
    public function __construct(
        array $paths,
        private readonly ?string $cachePath = null,
        ?EnvTemplate $template = null,
    ) {
        $this->paths = self::normalizeBasePaths($paths);
        $this->template = $template ?? new EnvTemplate;
    }

    public static function fromPaths(string ...$paths): self
    {
        return new self(paths: array_values($paths));
    }

    public function withTemplate(EnvTemplate $template): self
    {
        return new self(paths: $this->paths, cachePath: $this->cachePath, template: $template);
    }

    public function withCache(string $cachePath): self
    {
        return new self(paths: $this->paths, cachePath: $cachePath, template: $this->template);
    }

    public function load(string $config): ConfigEntry
    {
        $sourcePath = $this->resolveSourcePath($config);
        $rawData = $this->readSourceMemoized($sourcePath);
        // Resolution runs on every load, never on the memoized value, so `${VAR}`
        // placeholders still see the current environment.
        $data = $this->template->resolveData($rawData);

        return new ConfigEntry(
            key: ConfigKey::fromPath($sourcePath),
            sourcePath: $sourcePath,
            data: $data,
        );
    }

    /**
     * Parsing a preset YAML costs ~56us against ~1.9us for a memoized load, and callers
     * reload the same file once per request (LLMConfig::fromPreset does), so the parsed
     * form is held for the process. The mtime
     * is part of the key, which keeps editing a config file during development live at the
     * cost of one stat per load.
     *
     * @var array<string, array{mtime: int, data: array<array-key, mixed>}>
     */
    private static array $sourceMemo = [];

    /**
     * Drops the in-memory parsed-config cache. Intended for long-running workers and tests
     * that rewrite config files fast enough for mtime to be an unreliable discriminator.
     */
    public static function flushSourceCache(): void
    {
        self::$sourceMemo = [];
    }

    /** @return array<array-key, mixed> */
    private function readSourceMemoized(string $sourcePath): array
    {
        $mtime = self::sourceMtime($sourcePath);
        $memo = self::$sourceMemo[$sourcePath] ?? null;
        if ($memo !== null && $memo['mtime'] === $mtime) {
            return $memo['data'];
        }

        $data = match (true) {
            $this->cachePath === null => self::readSource($sourcePath),
            default => $this->readSourceWithCache($sourcePath),
        };
        self::$sourceMemo[$sourcePath] = ['mtime' => $mtime, 'data' => $data];

        return $data;
    }

    /** @return array<array-key, mixed> */
    private function readSourceWithCache(string $sourcePath): array
    {
        $sourceMtime = self::sourceMtime($sourcePath);
        $cachedPayload = $this->readCachePayload() ?? [
            '_meta' => ['version' => self::CACHE_VERSION],
            'entries' => [],
        ];
        $cachedEntry = $cachedPayload['entries'][$sourcePath] ?? null;

        if ($this->isCachedEntryFresh($cachedEntry, $sourceMtime)) {
            return $cachedEntry['data'];
        }

        $data = self::readSource($sourcePath);
        $cachedPayload['entries'][$sourcePath] = [
            'mtime' => $sourceMtime,
            'data' => $data,
        ];

        $this->writeCachePayload($cachedPayload);

        return $data;
    }

    /** @return array<string, mixed>|null */
    private function readCachePayload(): ?array
    {
        if ($this->cachePath === null || ! is_file($this->cachePath)) {
            return null;
        }

        try {
            $payload = self::requireFile($this->cachePath);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($payload)) {
            return null;
        }

        $meta = $payload['_meta'] ?? null;
        $entries = $payload['entries'] ?? null;
        if (! is_array($meta) || ! is_array($entries)) {
            return null;
        }
        if (($meta['version'] ?? null) !== self::CACHE_VERSION) {
            return null;
        }

        return $payload;
    }

    private function isCachedEntryFresh(mixed $entry, int $sourceMtime): bool
    {
        if (! is_array($entry)) {
            return false;
        }

        return match (true) {
            ($entry['mtime'] ?? null) !== $sourceMtime => false,
            ! isset($entry['data']) || ! is_array($entry['data']) => false,
            default => true,
        };
    }

    /** @param array<string, mixed> $payload */
    private function writeCachePayload(array $payload): void
    {
        if ($this->cachePath === null) {
            return;
        }

        $cacheDirectory = dirname($this->cachePath);
        if (! is_dir($cacheDirectory)) {
            if (! mkdir($cacheDirectory, 0777, true) && ! is_dir($cacheDirectory)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $cacheDirectory));
            }
        }

        $payload['_meta'] = ['version' => self::CACHE_VERSION];
        $export = var_export($payload, true);
        $content = "<?php declare(strict_types=1);\n\nreturn {$export};\n";
        $tempPath = $this->cachePath.'.'.bin2hex(random_bytes(8)).'.tmp';
        $result = file_put_contents($tempPath, $content, LOCK_EX);
        if ($result === false) {
            throw new RuntimeException("Failed to write temporary config cache file: {$tempPath}");
        }

        if (! rename($tempPath, $this->cachePath)) {
            @unlink($tempPath);
            throw new RuntimeException("Failed to atomically replace config cache file: {$this->cachePath}");
        }
    }

    /** @return array<array-key, mixed> */
    private static function readSource(string $sourcePath): array
    {
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        $data = match ($extension) {
            'yaml', 'yml' => Yaml::parseFile($sourcePath),
            'php' => self::requireFile($sourcePath),
            default => throw new InvalidArgumentException("Unsupported config extension: {$extension} in {$sourcePath}"),
        };

        if (is_array($data)) {
            return $data;
        }

        throw new InvalidArgumentException("Config file must return array-shaped data: {$sourcePath}");
    }

    private static function sourceMtime(string $sourcePath): int
    {
        if (! is_file($sourcePath)) {
            throw new InvalidArgumentException("Config file does not exist: {$sourcePath}");
        }

        $mtime = filemtime($sourcePath);
        if ($mtime === false) {
            throw new InvalidArgumentException("Cannot read modification time for config file: {$sourcePath}");
        }

        return $mtime;
    }

    private static function normalizePath(string $path): string
    {
        $real = realpath($path);

        return match (true) {
            is_string($real) && $real !== '' => $real,
            default => $path,
        };
    }

    /**
     * @param  array<string>  $paths
     * @return array<int, string>
     */
    private static function normalizeBasePaths(array $paths): array
    {
        $normalized = [];

        foreach ($paths as $path) {
            $trimmed = trim($path);
            if ($trimmed === '') {
                continue;
            }

            $normalizedPath = self::normalizePath($trimmed);
            if (! is_dir($normalizedPath)) {
                continue;
            }

            $normalized[rtrim($normalizedPath, '/\\')] = rtrim($normalizedPath, '/\\');
        }

        if ($normalized === []) {
            throw new InvalidArgumentException('Config requires at least one existing base path');
        }

        return array_values($normalized);
    }

    private function resolveSourcePath(string $config): string
    {
        $relativePath = self::sanitizeRelativePath($config);

        foreach ($this->paths as $basePath) {
            $sourcePath = $this->resolveWithinBasePath($basePath, $relativePath);
            if ($sourcePath === null) {
                continue;
            }

            return $sourcePath;
        }

        throw new InvalidArgumentException(
            "Config file '{$relativePath}' was not found in base paths: ".implode(', ', $this->paths),
        );
    }

    private function resolveWithinBasePath(string $basePath, string $relativePath): ?string
    {
        $candidate = $basePath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if (! is_file($candidate)) {
            return null;
        }

        $resolvedCandidate = self::normalizePath($candidate);
        $basePrefix = $basePath.DIRECTORY_SEPARATOR;
        if ($resolvedCandidate !== $basePath && ! str_starts_with($resolvedCandidate, $basePrefix)) {
            throw new InvalidArgumentException("Resolved config file escapes base path: {$relativePath}");
        }

        return $resolvedCandidate;
    }

    private static function sanitizeRelativePath(string $config): string
    {
        $trimmed = trim($config);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Config::load() requires a relative config file path');
        }

        if (self::isAbsolutePath($trimmed)) {
            throw new InvalidArgumentException("Config::load() accepts relative paths only: {$trimmed}");
        }

        $normalized = str_replace('\\', '/', $trimmed);
        $normalized = ltrim($normalized, '/');
        $segments = array_values(array_filter(explode('/', $normalized), static fn (string $segment): bool => $segment !== ''));

        if ($segments === []) {
            throw new InvalidArgumentException('Config::load() requires a relative config file path');
        }

        $safeSegments = [];
        foreach ($segments as $segment) {
            if ($segment === '..') {
                throw new InvalidArgumentException("Config path traversal is not allowed: {$config}");
            }
            if ($segment === '.') {
                continue;
            }
            $safeSegments[] = $segment;
        }

        if ($safeSegments === []) {
            throw new InvalidArgumentException('Config::load() requires a relative config file path');
        }

        return implode('/', $safeSegments);
    }

    private static function isAbsolutePath(string $path): bool
    {
        return match (true) {
            str_starts_with($path, '/') => true,
            str_starts_with($path, '\\\\') => true,
            preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1 => true,
            default => false,
        };
    }

    private static function requireFile(string $path): mixed
    {
        /** @psalm-suppress UnresolvableInclude */
        return require $path;
    }
}
