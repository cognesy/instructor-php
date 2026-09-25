<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Drivers\Typesense;

use Cognesy\Retrieval\Config\StoreConfig;
use Cognesy\Retrieval\Data\DistanceMetric;
use InvalidArgumentException;

final readonly class TypesenseConfig
{
    public function __construct(
        public string $endpoint,
        public string $collection,
        public int $dimensions,
        public DistanceMetric $metric = DistanceMetric::Cosine,
        public string $apiKey = '',
        public int $maxResponseBytes = 8_388_608,
        public int $maxScanPageSize = 250,
    ) {
        if ($endpoint === '' || $collection === '' || $dimensions < 1 || $maxResponseBytes < 1 || $maxScanPageSize < 1) {
            throw new InvalidArgumentException('Typesense endpoint, collection, dimensions, and positive limits are required');
        }
        if ($metric === DistanceMetric::Euclidean) {
            throw new InvalidArgumentException('Typesense does not support Euclidean vector distance');
        }
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
            maxScanPageSize: self::intOption($config, 'max_scan_page_size', 250),
        );
    }

    public function endpointUrl(string $path = ''): string
    {
        return rtrim($this->endpoint, '/').$path;
    }

    public function collectionUrl(string $path = ''): string
    {
        return $this->endpointUrl('/collections/'.rawurlencode($this->collection).$path);
    }

    /** @return positive-int */
    public function boundedScanPageSize(): int
    {
        return max(1, $this->maxScanPageSize);
    }

    private static function stringOption(StoreConfig $config, string $key, bool $required = true): string
    {
        $value = $config->options[$key] ?? '';
        if (! is_string($value) || ($required && $value === '')) {
            throw new InvalidArgumentException("Typesense option {$key} must be a non-empty string");
        }

        return $value;
    }

    private static function intOption(StoreConfig $config, string $key, ?int $default = null): int
    {
        $value = $config->options[$key] ?? $default;
        if (! is_int($value) || $value < 1) {
            throw new InvalidArgumentException("Typesense option {$key} must be a positive integer");
        }

        return $value;
    }
}
