<?php declare(strict_types=1);

namespace Cognesy\Config;

use InvalidArgumentException;
use LogicException;
use Throwable;

final class ConfigLoader
{
    private const CACHE_VERSION = 1;

    /** @var array<int, string> */
    private array $paths;
    private ?string $cachePath;
    /** @var array<string, ConfigEntry>|null */
    private ?array $entries = null;

    /**
     * @param array<int, string> $paths
     */
    private function __construct(array $paths, ?string $cachePath = null) {
        $this->paths = self::normalizePaths($paths);
        $this->cachePath = $cachePath;
    }

    public static function fromPaths(string ...$paths): self {
        return new self(paths: $paths);
    }

    public function withCache(string $cachePath): self {
        return new self(paths: $this->paths, cachePath: $cachePath);
    }

    public function load(string $key): ConfigEntry {
        $entries = $this->entries();
        if (!array_key_exists($key, $entries)) {
            throw new InvalidArgumentException("Config key not found: {$key}");
        }

        return $entries[$key];
    }

    public function has(string $key): bool {
        return array_key_exists($key, $this->entries());
    }

    /** @return array<int, string> */
    public function keys(): array {
        $keys = array_keys($this->entries());
        sort($keys);
        return $keys;
    }

    /** @return array<string, ConfigEntry> */
    public function all(): array {
        return $this->entries();
    }

    /** @return array<string, ConfigEntry> */
    private function entries(): array {
        if ($this->entries !== null) {
            return $this->entries;
        }

        $this->entries = match (true) {
            $this->cachePath === null => $this->buildEntries(),
            default => $this->loadEntriesWithCache(),
        };

        return $this->entries;
    }

    /** @return array<string, ConfigEntry> */
    private function loadEntriesWithCache(): array {
        $states = $this->fileStates();
        $payload = $this->readCachePayload();
        if ($this->isCacheFresh($payload, $states)) {
            try {
                return $this->entriesFromPayload($payload);
            } catch (Throwable) {
                // Rebuild below.
            }
        }

        $entries = $this->buildEntries();
        $this->writeCachePayload($this->buildCachePayload($entries, $states));
        return $entries;
    }

    /** @return array<string, ConfigEntry> */
    private function buildEntries(): array {
        $entries = [];
        foreach ($this->paths as $path) {
            $entry = (new Config(path: $path))->load();
            $key = $entry->key();
            if (array_key_exists($key, $entries)) {
                $existing = $entries[$key]->sourcePath();
                throw new LogicException("Duplicate config key '{$key}' for paths: {$existing} and {$entry->sourcePath()}");
            }
            $entries[$key] = $entry;
        }
        return $entries;
    }

    /** @return array<string, int> */
    private function fileStates(): array {
        $states = [];
        foreach ($this->paths as $path) {
            $normalizedPath = self::normalizePath($path);
            if (!is_file($normalizedPath)) {
                throw new InvalidArgumentException("Config file does not exist: {$normalizedPath}");
            }

            $mtime = filemtime($normalizedPath);
            if ($mtime === false) {
                throw new InvalidArgumentException("Cannot read modification time for config file: {$normalizedPath}");
            }

            $states[$normalizedPath] = $mtime;
        }
        return $states;
    }

    /** @param array<string, ConfigEntry> $entries @param array<string, int> $states */
    private function buildCachePayload(array $entries, array $states): array {
        $entryPayload = [];
        foreach ($entries as $key => $entry) {
            $entryPayload[$key] = [
                'source' => $entry->sourcePath(),
                'data' => $entry->toArray(),
            ];
        }

        return [
            '_meta' => [
                'version' => self::CACHE_VERSION,
                'files' => $states,
            ],
            'entries' => $entryPayload,
        ];
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

    /** @param array<string, int> $states */
    private function isCacheFresh(?array $payload, array $states): bool {
        if ($payload === null) {
            return false;
        }

        $meta = $payload['_meta'] ?? null;
        if (!is_array($meta)) {
            return false;
        }

        $cachedStates = $meta['files'] ?? null;
        if (!is_array($cachedStates)) {
            return false;
        }

        if (($meta['version'] ?? null) !== self::CACHE_VERSION) {
            return false;
        }

        if (count($cachedStates) !== count($states)) {
            return false;
        }

        foreach ($states as $path => $mtime) {
            if (($cachedStates[$path] ?? null) !== $mtime) {
                return false;
            }
        }

        return true;
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

    /** @return array<string, ConfigEntry> */
    private function entriesFromPayload(array $payload): array {
        $rawEntries = $payload['entries'] ?? null;
        if (!is_array($rawEntries)) {
            throw new LogicException('Malformed config cache payload: missing entries');
        }

        $entries = [];
        foreach ($rawEntries as $key => $raw) {
            if (!is_array($raw)) {
                throw new LogicException('Malformed config cache payload: invalid entry node');
            }

            $source = $raw['source'] ?? null;
            $data = $raw['data'] ?? null;
            if (!is_string($source) || !is_array($data)) {
                throw new LogicException('Malformed config cache payload: invalid entry fields');
            }

            $entries[(string) $key] = new ConfigEntry(
                key: (string) $key,
                sourcePath: $source,
                data: $data,
            );
        }

        return $entries;
    }

    /** @param array<int, string> $paths @return array<int, string> */
    private static function normalizePaths(array $paths): array {
        $normalized = [];
        foreach ($paths as $path) {
            if ($path === '') {
                continue;
            }
            $normalizedPath = self::normalizePath($path);
            $normalized[$normalizedPath] = $normalizedPath;
        }

        return array_values($normalized);
    }

    private static function normalizePath(string $path): string {
        $real = realpath($path);
        return match (true) {
            is_string($real) && $real !== '' => $real,
            default => $path,
        };
    }
}
