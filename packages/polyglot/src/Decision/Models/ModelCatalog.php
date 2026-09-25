<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Models;

use Cognesy\Config\BasePath;
use Cognesy\Config\Config;
use InvalidArgumentException;
use RuntimeException;

final class ModelCatalog
{
    private const array CONFIG_PATHS = [
        'config/sdm/models',
        'packages/polyglot/resources/config/sdm/models',
        'vendor/cognesy/instructor-php/packages/polyglot/resources/config/sdm/models',
        'vendor/cognesy/instructor-polyglot/resources/config/sdm/models',
    ];

    /** @var list<string> */
    private array $paths;

    private ?Config $config;

    /** @var array<string, DecisionModel> */
    private array $resolved = [];

    public function __construct(string ...$paths)
    {
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

    public static function discover(?string $basePath = null): self
    {
        /** @var array<string, self> $catalogs */
        static $catalogs = [];

        $basePath ??= BasePath::get();
        $basePath = realpath($basePath) ?: $basePath;

        return $catalogs[$basePath] ??= self::discoverForBasePath($basePath);
    }

    public static function fromPaths(string ...$paths): self
    {
        return new self(...$paths);
    }

    public function find(string $driver, string $model): DecisionModel
    {
        $key = self::lookupKey($driver, $model);

        return $this->resolved[$key] ??= $this->load($driver, $model)
            ?? DecisionModel::unknown($driver, $model);
    }

    public static function relativePath(string $driver, string $model): string
    {
        return self::segment($driver).'/'.self::segment($model).'.yaml';
    }

    private static function discoverForBasePath(string $basePath): self
    {
        $paths = array_map(
            static fn (string $path): string => rtrim($basePath, '/\\').'/'.$path,
            self::CONFIG_PATHS,
        );

        return new self(...[...$paths, __DIR__.'/../../../resources/config/sdm/models']);
    }

    private function load(string $driver, string $model): ?DecisionModel
    {
        $relativePath = self::relativePath($driver, $model);
        foreach ($this->paths as $path) {
            $candidate = $path.'/'.$relativePath;
            if (! file_exists($candidate) && ! is_link($candidate)) {
                continue;
            }
            if (! is_file($candidate) || ! is_readable($candidate)) {
                throw new RuntimeException("Decision model record is not a readable file: {$relativePath}");
            }

            return $this->hydrate($relativePath, $driver, $model);
        }

        return null;
    }

    private function hydrate(string $relativePath, string $driver, string $model): DecisionModel
    {
        $data = $this->config?->loadRaw($relativePath)->toArray()
            ?? throw new RuntimeException('Decision model catalog is unavailable.');
        self::assertFields($data, ['schemaVersion', 'version', 'profile'], 'decision model record');
        if (($data['schemaVersion'] ?? null) !== 1) {
            throw new InvalidArgumentException('Unsupported decision model record schemaVersion; expected integer 1.');
        }
        $version = $data['version'] ?? null;
        if (! is_string($version) || trim($version) === '') {
            throw new InvalidArgumentException('Decision model record requires a non-empty version.');
        }
        $profileData = $data['profile'] ?? null;
        if (! is_array($profileData) || ($profileData !== [] && array_is_list($profileData))) {
            throw new InvalidArgumentException('Decision model record profile must be an object.');
        }
        $profile = DecisionModel::fromArray($profileData, $version);
        if (! $profile->matches($driver, $model)) {
            throw new InvalidArgumentException("Decision model record identity does not match path: {$relativePath}");
        }

        return $profile;
    }

    /** @param list<string> $allowed */
    private static function assertFields(array $data, array $allowed, string $context): void
    {
        foreach (array_keys($data) as $field) {
            if (! is_string($field) || ! in_array($field, $allowed, true)) {
                throw new InvalidArgumentException("Unexpected {$context} field: {$field}");
            }
        }
    }

    private static function lookupKey(string $driver, string $model): string
    {
        return $driver."\0".$model;
    }

    private static function segment(string $value): string
    {
        return match ($value) {
            '.', '..' => str_replace('.', '%2E', $value),
            default => rawurlencode($value),
        };
    }
}
