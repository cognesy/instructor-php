<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Drivers\Meilisearch;

use Cognesy\Retrieval\Config\StoreConfig;
use Cognesy\Retrieval\Data\DistanceMetric;
use InvalidArgumentException;

final readonly class MeilisearchConfig
{
    public function __construct(
        public string $endpoint,
        public string $index,
        public int $dimensions,
        public DistanceMetric $metric = DistanceMetric::Cosine,
        public string $apiKey = '',
        public string $embedder = 'instructor',
        public int $maxResponseBytes = 8_388_608,
        public int $taskTimeoutMilliseconds = 30_000,
        public int $taskPollMilliseconds = 25,
        public int $maxScanPageSize = 1_000,
    ) {
        if ($endpoint === '' || $index === '' || $dimensions < 1 || $embedder === '') {
            throw new InvalidArgumentException('Meilisearch endpoint, index, dimensions, and embedder are required');
        }
        if ($maxResponseBytes < 1 || $taskTimeoutMilliseconds < 1 || $taskPollMilliseconds < 0 || $maxScanPageSize < 1) {
            throw new InvalidArgumentException('Meilisearch response, task, and scan limits must be valid');
        }
        if ($metric !== DistanceMetric::Cosine) {
            throw new InvalidArgumentException('Meilisearch supports cosine vector similarity only');
        }
    }

    public static function fromStoreConfig(StoreConfig $config): self
    {
        return new self(
            endpoint: self::stringOption($config, 'endpoint'),
            index: self::stringOption($config, 'index'),
            dimensions: self::intOption($config, 'dimensions'),
            metric: $config->metric,
            apiKey: self::stringOption($config, 'api_key', false),
            embedder: self::stringOption($config, 'embedder', default: 'instructor'),
            maxResponseBytes: self::intOption($config, 'max_response_bytes', 8_388_608),
            taskTimeoutMilliseconds: self::intOption($config, 'task_timeout_milliseconds', 30_000),
            taskPollMilliseconds: self::intOption($config, 'task_poll_milliseconds', 25, false),
            maxScanPageSize: self::intOption($config, 'max_scan_page_size', 1_000),
        );
    }

    public function endpointUrl(string $path = ''): string
    {
        return rtrim($this->endpoint, '/').$path;
    }

    public function indexUrl(string $path = ''): string
    {
        return $this->endpointUrl('/indexes/'.rawurlencode($this->index).$path);
    }

    /** @return positive-int */
    public function boundedScanPageSize(): int
    {
        return max(1, $this->maxScanPageSize);
    }

    private static function stringOption(
        StoreConfig $config,
        string $key,
        bool $required = true,
        string $default = '',
    ): string {
        $value = $config->options[$key] ?? $default;
        if (! is_string($value) || ($required && $value === '')) {
            throw new InvalidArgumentException("Meilisearch option {$key} must be a non-empty string");
        }

        return $value;
    }

    private static function intOption(
        StoreConfig $config,
        string $key,
        ?int $default = null,
        bool $positive = true,
    ): int {
        $value = $config->options[$key] ?? $default;
        if (! is_int($value) || ($positive ? $value < 1 : $value < 0)) {
            throw new InvalidArgumentException("Meilisearch option {$key} must be a valid integer");
        }

        return $value;
    }
}
