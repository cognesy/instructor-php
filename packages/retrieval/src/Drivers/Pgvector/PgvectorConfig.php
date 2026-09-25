<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Drivers\Pgvector;

use Cognesy\Retrieval\Config\StoreConfig;
use Cognesy\Retrieval\Data\DistanceMetric;
use InvalidArgumentException;

final readonly class PgvectorConfig
{
    public function __construct(
        public string $table,
        public int $dimensions,
        public DistanceMetric $metric = DistanceMetric::Cosine,
        public bool $approximate = false,
        public bool $relaxedOrdering = false,
    ) {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?$/', $table)) {
            throw new InvalidArgumentException('Pgvector table must be a safe schema-qualified identifier');
        }
        if ($dimensions < 1) {
            throw new InvalidArgumentException('Pgvector dimensions must be positive');
        }
        if ($relaxedOrdering) {
            throw new InvalidArgumentException('Pgvector relaxed ordering is not supported; results are always strictly ordered');
        }
    }

    public static function fromStoreConfig(StoreConfig $config): self
    {
        $table = $config->options['table'] ?? '';
        $dimensions = $config->options['dimensions'] ?? null;
        if (! is_string($table) || ! is_int($dimensions)) {
            throw new InvalidArgumentException('Pgvector options table and dimensions are required');
        }

        return new self(
            $table,
            $dimensions,
            $config->metric,
            ($config->options['approximate'] ?? false) === true,
            ($config->options['relaxed_ordering'] ?? false) === true,
        );
    }

    public function quotedTable(): string
    {
        return implode('.', array_map(
            static fn (string $part): string => '"'.$part.'"',
            explode('.', $this->table),
        ));
    }

    public function quotedIndex(): string
    {
        return '"'.str_replace('.', '_', $this->table).'_embedding_hnsw_idx"';
    }
}
