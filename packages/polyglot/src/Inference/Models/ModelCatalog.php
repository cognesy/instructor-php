<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Models;

use ArrayIterator;
use Cognesy\Config\BasePath;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use JsonException;
use RuntimeException;
use Traversable;

/** @implements IteratorAggregate<int, ModelProfile> */
final class ModelCatalog implements Countable, IteratorAggregate
{
    private const CONFIG_PATHS = [
        'config/llm/models',
        'packages/polyglot/resources/config/llm/models',
        'vendor/cognesy/instructor-php/packages/polyglot/resources/config/llm/models',
        'vendor/cognesy/instructor-polyglot/resources/config/llm/models',
    ];

    /** @var array<string, ModelProfile> */
    private array $profiles;
    /** @var array<string, ModelProfile|null> */
    private array $resolved = [];
    /** @var array<string, ModelProfile> */
    private array $unknown = [];
    /** @var array<string, ModelProfile>|null */
    private ?array $enumerated = null;

    /** @param iterable<ModelProfile> $profiles @param list<self> $layers */
    public function __construct(
        iterable $profiles = [],
        public readonly string $version = '',
        private readonly ?ModelRecordDirectory $records = null,
        private readonly array $layers = [],
    ) {
        $indexed = [];
        foreach ($profiles as $profile) {
            $indexed[$profile->key->lookupKey()] = $profile;
        }
        ksort($indexed, SORT_STRING);
        $this->profiles = $indexed;
    }

    public static function discover(?string $basePath = null): self {
        /** @var array<string, self> $catalogs */
        static $catalogs = [];

        $basePath ??= BasePath::get();
        $basePath = realpath($basePath) ?: $basePath;

        return $catalogs[$basePath] ??= self::discoverForBasePath($basePath);
    }

    private static function discoverForBasePath(string $basePath): self {
        $paths = array_map(
            static fn (string $path): string => rtrim($basePath, '/\\') . '/' . $path,
            self::CONFIG_PATHS,
        );

        return self::fromPaths(...[...$paths, __DIR__ . '/../../../resources/config/llm/models']);
    }

    public static function fromPaths(string ...$paths): self {
        return new self(records: new ModelRecordDirectory(...$paths));
    }

    public static function fromFile(string $path): self {
        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException("Unable to read model catalog: {$path}");
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new InvalidArgumentException(
                message: "Invalid model catalog JSON: {$path}",
                previous: $error,
            );
        }

        if (!is_array($data)) {
            throw new InvalidArgumentException("Model catalog must contain an object: {$path}");
        }

        return self::fromArray($data);
    }

    public static function fromArray(array $data): self {
        ModelRecordFields::validate($data, ['schemaVersion', 'version', 'models'], 'catalog');
        ModelRecordFields::schemaVersion($data['schemaVersion'] ?? 1);
        $version = $data['version'] ?? '';
        $models = $data['models'] ?? null;
        if (!is_string($version) || !is_array($models) || !array_is_list($models)) {
            throw new InvalidArgumentException('Model catalog requires string version and models list.');
        }

        $profiles = [];
        foreach ($models as $model) {
            if (!is_array($model)) {
                throw new InvalidArgumentException('Every model catalog entry must be an object.');
            }
            $profile = ModelProfile::fromArray($model, $version);
            $lookupKey = $profile->key->lookupKey();
            if (isset($profiles[$lookupKey])) {
                throw new InvalidArgumentException(
                    "Duplicate model catalog entry: {$profile->key->toString()}",
                );
            }
            $profiles[$lookupKey] = $profile;
        }

        return new self($profiles, $version);
    }

    public function find(string $driver, string $model): ModelProfile {
        $key = new ModelKey($driver, $model);

        return $this->resolve($key)
            ?? $this->unknown[$key->lookupKey()] ??= ModelProfile::unknown($key, $this->version);
    }

    public function forDriver(string $driver): self {
        $profiles = array_filter(
            $this->all(),
            static fn (ModelProfile $profile): bool => $profile->key->driver === $driver,
        );

        return new self($profiles, $this->version);
    }

    public function overlay(self $higherPriority): self {
        return new self(
            layers: [$higherPriority, $this],
            version: match ($higherPriority->version) {
                '' => $this->version,
                default => $higherPriority->version,
            },
        );
    }

    public function count(): int {
        return count($this->all());
    }

    /** @return Traversable<int, ModelProfile> */
    public function getIterator(): Traversable {
        return new ArrayIterator(array_values($this->all()));
    }

    /** @return array{version: string, models: list<array<string, mixed>>} */
    public function toArray(): array {
        $profiles = $this->all();
        return [
            'version' => $this->exportVersion($profiles),
            'models' => array_map(
                static fn (ModelProfile $profile): array => $profile->toArray(),
                array_values($profiles),
            ),
        ];
    }

    /** @param array<string, ModelProfile> $profiles */
    private function exportVersion(array $profiles): string {
        if ($this->version !== '') {
            return $this->version;
        }
        $versions = array_values(array_unique(array_map(
            static fn (ModelProfile $profile): string => $profile->catalogVersion,
            $profiles,
        )));

        return match (count($versions)) {
            1 => $versions[0],
            default => '',
        };
    }

    private function resolve(ModelKey $key): ?ModelProfile {
        $id = $key->lookupKey();
        if (array_key_exists($id, $this->resolved)) {
            return $this->resolved[$id];
        }

        return $this->resolved[$id] = $this->resolveUncached($key);
    }

    private function resolveUncached(ModelKey $key): ?ModelProfile {
        foreach ($this->layers as $layer) {
            $profile = $layer->resolve($key);
            if ($profile !== null) {
                return $profile;
            }
        }

        return $this->profiles[$key->lookupKey()] ?? $this->records?->find($key);
    }

    /** @return array<string, ModelProfile> */
    private function all(): array {
        if ($this->enumerated !== null) {
            return $this->enumerated;
        }
        $profiles = $this->profiles;
        foreach ($this->keys() as $key) {
            $profiles[$key->lookupKey()] = $this->find($key->driver, $key->model);
        }
        ksort($profiles, SORT_STRING);

        return $this->enumerated = $profiles;
    }

    /** @return iterable<ModelKey> */
    private function keys(): iterable {
        foreach ($this->profiles as $profile) {
            yield $profile->key;
        }
        foreach ($this->layers as $layer) {
            yield from $layer->keys();
        }

        yield from $this->records?->keys() ?? [];
    }
}
