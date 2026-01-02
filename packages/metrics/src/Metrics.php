<?php declare(strict_types=1);

namespace Cognesy\Metrics;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Metrics\Contracts\CanCollectMetrics;
use Cognesy\Metrics\Contracts\CanExportMetrics;
use Cognesy\Metrics\Contracts\CanStoreMetrics;
use Cognesy\Metrics\Registry\InMemoryRegistry;

/**
 * Main entry point for metrics collection.
 *
 * Coordinates collectors, registry, and exporters. Collectors subscribe
 * to domain events and record metrics. Exporters push metrics to backends.
 */
final class Metrics
{
    private CanStoreMetrics $registry;
    /** @var list<CanExportMetrics> */
    private array $exporters = [];

    public function __construct(
        private CanHandleEvents $events,
        ?CanStoreMetrics $registry = null,
    ) {
        $this->registry = $registry ?? new InMemoryRegistry();
    }

    /**
     * Register a collector that listens to events and records metrics.
     */
    public function collect(CanCollectMetrics $collector): self {
        $collector->register($this->events, $this->registry);
        return $this;
    }

    /**
     * Add an exporter to receive metrics on export.
     */
    public function exportTo(CanExportMetrics $exporter): self {
        $this->exporters[] = $exporter;
        return $this;
    }

    /**
     * Push all collected metrics to registered exporters.
     */
    public function export(): void {
        $metrics = $this->registry->all();
        foreach ($this->exporters as $exporter) {
            $exporter->export($metrics);
        }
    }

    /**
     * Access the underlying registry for direct metric recording or queries.
     */
    public function registry(): CanStoreMetrics {
        return $this->registry;
    }

    /**
     * Clear all collected metrics from the registry.
     */
    public function clear(): void {
        $this->registry->clear();
    }
}
