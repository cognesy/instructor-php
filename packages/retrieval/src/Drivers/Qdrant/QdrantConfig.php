<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Drivers\Qdrant;

use Cognesy\Retrieval\Config\StoreConfig;
use Cognesy\Retrieval\Data\DistanceMetric;
use InvalidArgumentException;

final readonly class QdrantConfig
{
    public function __construct(
        public string $endpoint,
        public string $collection,
        public int $dimensions,
        public DistanceMetric $metric = DistanceMetric::Cosine,
        public string $apiKey = '',
        public int $maxResponseBytes = 8_388_608,
    ) {
        if ($endpoint === '' || $collection === '' || $dimensions < 1 || $maxResponseBytes < 1) {
            throw new InvalidArgumentException('Qdrant endpoint, collection, dimensions, and response limit are required');
        }
    }

    public function collectionUrl(string $path = ''): string
    {
        return rtrim($this->endpoint, '/').'/collections/'.rawurlencode($this->collection).$path;
    }

    public static function fromStoreConfig(StoreConfig $config): self
    {
        return new self(
            endpoint: self::stringOption($config, 'endpoint'),
            collection: self::stringOption($config, 'collection'),
            dimensions: self::intOption($config, 'dimensions'),
            metric: $config->metric,
            apiKey: self::stringOption($config, 'api_key', false),
            maxResponseBytes: self::intOption($config, 'max_response_bytes', 8_388_608),
        );
    }

    private static function stringOption(StoreConfig $config, string $key, bool $required = true): string
    {
        $value = $config->options[$key] ?? '';
        if (! is_string($value) || ($required && $value === '')) {
            throw new InvalidArgumentException("Qdrant option {$key} must be a non-empty string");
        }

        return $value;
    }

    private static function intOption(StoreConfig $config, string $key, ?int $default = null): int
    {
        $value = $config->options[$key] ?? $default;
        if (! is_int($value) || $value < 1) {
            throw new InvalidArgumentException("Qdrant option {$key} must be a positive integer");
        }

        return $value;
    }
}
