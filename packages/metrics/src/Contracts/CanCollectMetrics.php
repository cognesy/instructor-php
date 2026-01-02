<?php declare(strict_types=1);

namespace Cognesy\Metrics\Contracts;

use Cognesy\Events\Contracts\CanHandleEvents;

/**
 * Collector that subscribes to events and records metrics.
 */
interface CanCollectMetrics
{
    /**
     * Register event listeners and set up the collector.
     */
    public function register(
        CanHandleEvents $events,
        CanStoreMetrics $registry,
    ): void;
}
