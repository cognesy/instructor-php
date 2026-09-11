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
final readonly class ModelCatalog implements Countable, IteratorAggregate
{
    private const CONFIG_PATHS = [
        'config/llm/models.json',
        'packages/polyglot/resources/config/llm/models.json',
        'vendor/cognesy/instructor-php/packages/polyglot/resources/config/llm/models.json',
        'vendor/cognesy/instructor-polyglot/resources/config/llm/models.json',
        __DIR__ . '/../../../resources/config/llm/models.json',
    ];

    /** @var array<string, ModelProfile> */
    private array $profiles;

    /** @param iterable<ModelProfile> $profiles */
    public function __construct(
        iterable $profiles = [],
        public string $version = '',
    ) {
        $indexed = [];
        foreach ($profiles as $profile) {
            $indexed[$profile->key->lookupKey()] = $profile;
        }
        ksort($indexed, SORT_STRING);
        $this->profiles = $indexed;
    }

    public static function bundled(): self {
        static $catalog = null;

        return $catalog ??= self::fromFile(
            __DIR__ . '/../../../resources/config/llm/models.json',
        );
    }

    public static function discover(): self {
        /** @var array<string, self> $catalogs */
        static $catalogs = [];

        $basePath = BasePath::get();

        return $catalogs[$basePath] ??= self::discoverForCurrentBasePath();
    }

    private static function discoverForCurrentBasePath(): self {
        $catalog = new self();
        $paths = array_reverse(BasePath::resolveExisting(...self::CONFIG_PATHS));
        foreach ($paths as $path) {
            $catalog = $catalog->overlay(self::fromFile($path));
        }

        return $catalog;
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

        return $this->profiles[$key->lookupKey()] ?? ModelProfile::unknown($key, $this->version);
    }

    public function forDriver(string $driver): self {
        $profiles = array_filter(
            $this->profiles,
            static fn (ModelProfile $profile): bool => $profile->key->driver === $driver,
        );

        return new self($profiles, $this->version);
    }

    public function overlay(self $higherPriority): self {
        return new self(
            profiles: [...$this->profiles, ...$higherPriority->profiles],
            version: match ($higherPriority->version) {
                '' => $this->version,
                default => $higherPriority->version,
            },
        );
    }

    public function count(): int {
        return count($this->profiles);
    }

    /** @return Traversable<int, ModelProfile> */
    public function getIterator(): Traversable {
        return new ArrayIterator(array_values($this->profiles));
    }

    /** @return array{version: string, models: list<array<string, mixed>>} */
    public function toArray(): array {
        return [
            'version' => $this->version,
            'models' => array_map(
                static fn (ModelProfile $profile): array => $profile->toArray(),
                array_values($this->profiles),
            ),
        ];
    }
}
