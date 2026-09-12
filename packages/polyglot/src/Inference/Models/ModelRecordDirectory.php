<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Models;

use Cognesy\Config\Config;
use InvalidArgumentException;
use RuntimeException;

/** Direct named-config access; enumeration is separate from exact lookup. */
final readonly class ModelRecordDirectory
{
    private ?Config $config;
    /** @var list<string> */
    private array $paths;

    public function __construct(string ...$paths) {
        $resolved = array_map(static fn (string $path): string|false => realpath($path), $paths);
        $this->paths = array_values(array_unique(array_filter(
            $resolved,
            static fn (string|false $path): bool => is_string($path) && is_dir($path),
        )));
        $this->config = match ($this->paths) {
            [] => null,
            default => Config::fromPaths(...$this->paths),
        };
    }

    public static function relativePath(ModelKey $key): string {
        return self::segment($key->driver) . '/' . self::segment($key->model) . '.yaml';
    }

    public function find(ModelKey $key): ?ModelProfile {
        $relativePath = self::relativePath($key);
        foreach ($this->paths as $path) {
            if (!file_exists($path . '/' . $relativePath) && !is_link($path . '/' . $relativePath)) {
                continue;
            }
            if (!is_file($path . '/' . $relativePath) || !is_readable($path . '/' . $relativePath)) {
                throw new RuntimeException("Model record is not a readable file: {$relativePath}");
            }

            return $this->load($relativePath, $key);
        }

        return null;
    }

    private static function segment(string $value): string {
        return match ($value) {
            '.', '..' => str_replace('.', '%2E', $value),
            default => rawurlencode($value),
        };
    }

    /** @return iterable<ModelKey> */
    public function keys(): iterable {
        foreach ($this->paths as $path) {
            yield from $this->keysIn($path);
        }
    }

    private function load(string $relativePath, ModelKey $key): ModelProfile {
        $data = $this->config?->loadRaw($relativePath)->toArray()
            ?? throw new RuntimeException('Model record directory is unavailable.');
        ModelRecordFields::validate($data, ['schemaVersion', 'version', 'profile'], 'record');
        ModelRecordFields::schemaVersion($data['schemaVersion'] ?? null);
        $version = $data['version'] ?? null;
        if (!is_string($version) || $version === '') {
            throw new InvalidArgumentException("Model record requires a non-empty version: {$relativePath}");
        }
        $profile = ModelProfile::fromArray(ModelRecordFields::object($data, 'profile', 'record'), $version);
        if (!$profile->key->matches($key->driver, $key->model)) {
            throw new InvalidArgumentException("Model record identity does not match path: {$relativePath}");
        }

        return $profile;
    }

    /** @return iterable<ModelKey> */
    private function keysIn(string $path): iterable {
        foreach (glob($path . '/*/*.yaml') ?: [] as $file) {
            $key = new ModelKey(rawurldecode(basename(dirname($file))), rawurldecode(basename($file, '.yaml')));
            if ($path . '/' . self::relativePath($key) !== $file) {
                throw new InvalidArgumentException("Non-canonical model record path: {$file}");
            }

            yield $key;
        }
    }
}
