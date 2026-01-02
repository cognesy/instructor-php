<?php declare(strict_types=1);

namespace Cognesy\Metrics\Exporters;

use Cognesy\Metrics\Contracts\CanExportMetrics;
use Cognesy\Metrics\Data\Metric;

/**
 * Null exporter that discards all metrics.
 *
 * Useful for testing or when metrics export should be disabled.
 */
final class NullExporter implements CanExportMetrics
{
    /** @param iterable<Metric> $metrics */
    public function export(iterable $metrics): void {
        // Intentionally empty - metrics are discarded
    }
}
