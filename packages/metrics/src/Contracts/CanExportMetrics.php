<?php declare(strict_types=1);

namespace Cognesy\Metrics\Contracts;

use Cognesy\Metrics\Data\Metric;

/**
 * Exporter that outputs metrics to a backend.
 */
interface CanExportMetrics
{
    /**
     * Export metrics to the backend.
     *
     * @param iterable<Metric> $metrics
     */
    public function export(iterable $metrics): void;
}
