<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Config;

use Cognesy\Retrieval\Data\DistanceMetric;

final readonly class StoreConfig
{
    /** @param array<string, mixed> $options */
    public function __construct(
        public string $driver = 'memory',
        public DistanceMetric $metric = DistanceMetric::Cosine,
        public array $options = [],
    ) {}

    public function withDriver(string $driver): self
    {
        return new self($driver, $this->metric, $this->options);
    }

    public function withMetric(DistanceMetric $metric): self
    {
        return new self($this->driver, $metric, $this->options);
    }

    /** @param array<string, mixed> $options */
    public function withOptions(array $options): self
    {
        return new self($this->driver, $this->metric, $options);
    }
}
