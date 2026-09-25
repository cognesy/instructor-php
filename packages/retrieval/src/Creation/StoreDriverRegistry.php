<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Creation;

use Closure;
use Cognesy\Http\HttpClient;
use Cognesy\Retrieval\Config\StoreConfig;
use Cognesy\Retrieval\Contracts\CanProvideStoreDrivers;
use Cognesy\Retrieval\Contracts\CanStoreDocuments;
use Cognesy\Retrieval\Drivers\InMemory\InMemoryStore;
use Cognesy\Retrieval\Drivers\Meilisearch\MeilisearchConfig;
use Cognesy\Retrieval\Drivers\Meilisearch\MeilisearchStore;
use Cognesy\Retrieval\Drivers\Milvus\MilvusConfig;
use Cognesy\Retrieval\Drivers\Milvus\MilvusStore;
use Cognesy\Retrieval\Drivers\Pgvector\PgvectorConfig;
use Cognesy\Retrieval\Drivers\Pgvector\PgvectorStore;
use Cognesy\Retrieval\Drivers\Qdrant\QdrantConfig;
use Cognesy\Retrieval\Drivers\Qdrant\QdrantStore;
use Cognesy\Retrieval\Drivers\Typesense\TypesenseConfig;
use Cognesy\Retrieval\Drivers\Typesense\TypesenseStore;
use Cognesy\Retrieval\Drivers\Weaviate\WeaviateConfig;
use Cognesy\Retrieval\Drivers\Weaviate\WeaviateStore;
use InvalidArgumentException;
use Override;
use PDO;

final class StoreDriverRegistry implements CanProvideStoreDrivers
{
    /** @param array<string, callable(StoreConfig): CanStoreDocuments> $drivers */
    private function __construct(private array $drivers = []) {}

    public static function make(): self
    {
        return new self;
    }

    public static function default(): self
    {
        return self::fromArray([
            'memory' => static fn (StoreConfig $config): CanStoreDocuments => new InMemoryStore($config->metric),
            'meilisearch' => static fn (StoreConfig $config): CanStoreDocuments => new MeilisearchStore(
                MeilisearchConfig::fromStoreConfig($config),
                HttpClient::default(),
            ),
            'milvus' => static fn (StoreConfig $config): CanStoreDocuments => new MilvusStore(
                MilvusConfig::fromStoreConfig($config),
                HttpClient::default(),
            ),
            'pgvector' => static function (StoreConfig $config): CanStoreDocuments {
                $pdo = $config->options['pdo'] ?? null;
                if (! $pdo instanceof PDO) {
                    throw new InvalidArgumentException('Pgvector driver requires a PDO instance in store option pdo');
                }

                return new PgvectorStore(PgvectorConfig::fromStoreConfig($config), $pdo);
            },
            'qdrant' => static fn (StoreConfig $config): CanStoreDocuments => new QdrantStore(
                QdrantConfig::fromStoreConfig($config),
                HttpClient::default(),
            ),
            'typesense' => static fn (StoreConfig $config): CanStoreDocuments => new TypesenseStore(
                TypesenseConfig::fromStoreConfig($config),
                HttpClient::default(),
            ),
            'weaviate' => static fn (StoreConfig $config): CanStoreDocuments => new WeaviateStore(
                WeaviateConfig::fromStoreConfig($config),
                HttpClient::default(),
            ),
        ]);
    }

    /** @param array<string, class-string<CanStoreDocuments>|callable(StoreConfig): CanStoreDocuments> $drivers */
    public static function fromArray(array $drivers): self
    {
        $factories = [];
        foreach ($drivers as $name => $driver) {
            $factories[$name] = self::toFactory($driver);
        }

        return new self($factories);
    }

    /** @param class-string<CanStoreDocuments>|callable(StoreConfig): CanStoreDocuments $driver */
    public function withDriver(string $name, string|callable $driver): self
    {
        $copy = clone $this;
        $copy->drivers[$name] = self::toFactory($driver);

        return $copy;
    }

    public function withoutDriver(string $name): self
    {
        $copy = clone $this;
        unset($copy->drivers[$name]);

        return $copy;
    }

    #[Override]
    public function has(string $name): bool
    {
        return isset($this->drivers[$name]);
    }

    #[Override]
    public function driverNames(): array
    {
        return array_keys($this->drivers);
    }

    #[Override]
    public function makeDriver(string $name, StoreConfig $config): CanStoreDocuments
    {
        $factory = $this->drivers[$name] ?? null;
        if ($factory === null) {
            throw new InvalidArgumentException("Store driver not supported: {$name}");
        }

        return $factory($config);
    }

    /**
     * @param  class-string<CanStoreDocuments>|callable(StoreConfig): CanStoreDocuments  $driver
     * @return Closure(StoreConfig): CanStoreDocuments
     */
    private static function toFactory(string|callable $driver): Closure
    {
        return match (true) {
            is_string($driver) => static function (StoreConfig $config) use ($driver): CanStoreDocuments {
                $instance = new $driver($config);
                if (! $instance instanceof CanStoreDocuments) {
                    throw new InvalidArgumentException('Store driver class must implement '.CanStoreDocuments::class);
                }

                return $instance;
            },
            default => static function (StoreConfig $config) use ($driver): CanStoreDocuments {
                $instance = $driver($config);
                if (! $instance instanceof CanStoreDocuments) {
                    throw new InvalidArgumentException('Store driver factory must return '.CanStoreDocuments::class);
                }

                return $instance;
            },
        };
    }
}
