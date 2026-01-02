<?php declare(strict_types=1);

namespace Cognesy\Metrics\Exporters;

use Closure;
use Cognesy\Metrics\Contracts\CanExportMetrics;
use Cognesy\Metrics\Data\Metric;

/**
 * Callback exporter for custom metric handling.
 *
 * Passes metrics to a user-defined callback for flexible
 * integration with custom backends or processing pipelines.
 */
final class CallbackExporter implements CanExportMetrics
{
    /** @var Closure(iterable<Metric>): void */
    private Closure $callback;

    /**
     * @param callable(iterable<Metric>): void $callback
     */
    public function __construct(callable $callback) {
        $this->callback = $callback(...);
    }

    /** @param iterable<Metric> $metrics */
    public function export(iterable $metrics): void {
        ($this->callback)($metrics);
    }
}
