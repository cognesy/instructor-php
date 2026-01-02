<?php declare(strict_types=1);

namespace Cognesy\Metrics\Collectors;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Metrics\Contracts\CanCollectMetrics;
use Cognesy\Metrics\Contracts\CanStoreMetrics;
use Cognesy\Metrics\Data\Counter;
use Cognesy\Metrics\Data\Gauge;
use Cognesy\Metrics\Data\Histogram;
use Cognesy\Metrics\Data\Tags;
use Cognesy\Metrics\Data\Timer;
use RuntimeException;

/**
 * Base class for metrics collectors with convenience methods.
 *
 * Extend this class and implement listeners() to create
 * domain-specific collectors that subscribe to events.
 */
abstract class MetricsCollector implements CanCollectMetrics
{
    protected CanStoreMetrics $registry;

    /**
     * Returns event class => method name mapping.
     *
     * @return array<class-string, string>
     */
    abstract protected function listeners(): array;

    #[\Override]
    public function register(
        CanHandleEvents $events,
        CanStoreMetrics $registry,
    ): void {
        $this->registry = $registry;

        foreach ($this->listeners() as $eventClass => $method) {
            if (!method_exists($this, $method)) {
                throw new RuntimeException(
                    sprintf('Method %s::%s does not exist', static::class, $method)
                );
            }
            $events->addListener($eventClass, [$this, $method]);
        }
    }

    /**
     * Record a counter increment.
     *
     * @param array<string, string|int|float|bool> $tags
     */
    protected function counter(string $name, array $tags = [], float $increment = 1): Counter {
        return $this->registry->counter($name, Tags::of($tags), $increment);
    }

    /**
     * Record a gauge value.
     *
     * @param array<string, string|int|float|bool> $tags
     */
    protected function gauge(string $name, float $value, array $tags = []): Gauge {
        return $this->registry->gauge($name, Tags::of($tags), $value);
    }

    /**
     * Record a histogram value.
     *
     * @param array<string, string|int|float|bool> $tags
     */
    protected function histogram(string $name, float $value, array $tags = []): Histogram {
        return $this->registry->histogram($name, Tags::of($tags), $value);
    }

    /**
     * Record a timer duration.
     *
     * @param array<string, string|int|float|bool> $tags
     */
    protected function timer(string $name, float $durationMs, array $tags = []): Timer {
        return $this->registry->timer($name, Tags::of($tags), $durationMs);
    }
}
