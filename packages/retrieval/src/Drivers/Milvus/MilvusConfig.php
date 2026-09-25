<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Drivers\Milvus;

use Cognesy\Retrieval\Config\StoreConfig;
use Cognesy\Retrieval\Data\DistanceMetric;
use InvalidArgumentException;

final readonly class MilvusConfig
{
    public function __construct(
        public string $endpoint,
        public string $collection,
        public int $dimensions,
        public DistanceMetric $metric = DistanceMetric::Cosine,
        public string $token = '',
        public string $database = '',
        public int $maxResponseBytes = 8_388_608,
        public int $maxFetchIds = 1_000,
        public int $maxContentBytes = 65_535,
    ) {
        if ($endpoint === '' || $dimensions < 2 || $dimensions > 32_768) {
            throw new InvalidArgumentException('Milvus endpoint and vector dimensions from 2 through 32768 are required');
        }
        if (! self::isIdentifier($collection) || ($database !== '' && ! self::isIdentifier($database))) {
            throw new InvalidArgumentException('Milvus collection and database must be valid identifiers');
        }
        if ($maxResponseBytes < 1 || $maxFetchIds < 1 || $maxContentBytes < 1 || $maxContentBytes > 65_535) {
            throw new InvalidArgumentException('Milvus response, fetch, and content limits must be valid');
        }
    }

    public static function fromStoreConfig(StoreConfig $config): self
    {
        return new self(
            endpoint: self::stringOption($config, 'endpoint'),
            collection: self::stringOption($config, 'collection'),
            dimensions: self::intOption($config, 'dimensions'),
            metric: $config->metric,
            token: self::stringOption($config, 'token', false),
            database: self::stringOption($config, 'database', false),
            maxResponseBytes: self::intOption($config, 'max_response_bytes', 8_388_608),
            maxFetchIds: self::intOption($config, 'max_fetch_ids', 1_000),
            maxContentBytes: self::intOption($config, 'max_content_bytes', 65_535),
        );
    }

    public function endpointUrl(string $path = ''): string
    {
        return rtrim($this->endpoint, '/').$path;
    }

    /** @return positive-int */
    public function boundedFetchIds(): int
    {
        return max(1, $this->maxFetchIds);
    }

    private static function isIdentifier(string $value): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,254}$/', $value) === 1;
    }

    private static function stringOption(StoreConfig $config, string $key, bool $required = true): string
    {
        $value = $config->options[$key] ?? '';
        if (! is_string($value) || ($required && $value === '')) {
            throw new InvalidArgumentException("Milvus option {$key} must be a non-empty string");
        }

        return $value;
    }

    private static function intOption(StoreConfig $config, string $key, ?int $default = null): int
    {
        $value = $config->options[$key] ?? $default;
        if (! is_int($value) || $value < 1) {
            throw new InvalidArgumentException("Milvus option {$key} must be a positive integer");
        }

        return $value;
    }
}
