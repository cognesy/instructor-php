<?php declare(strict_types=1);

namespace Cognesy\Metrics\Registry;

use Cognesy\Metrics\Contracts\CanStoreMetrics;
use Cognesy\Metrics\Data\Counter;
use Cognesy\Metrics\Data\Gauge;
use Cognesy\Metrics\Data\Histogram;
use Cognesy\Metrics\Data\Metric;
use Cognesy\Metrics\Data\Tags;
use Cognesy\Metrics\Data\Timer;
use DateTimeImmutable;

/**
 * In-memory metrics registry.
 *
 * Stores all metrics in memory. Suitable for short-lived
 * processes or when metrics are exported frequently.
 */
final class InMemoryRegistry implements CanStoreMetrics
{
    /** @var list<Metric> */
    private array $metrics = [];

    public function counter(string $name, Tags $tags, float $increment = 1): Counter {
        $metric = new Counter($name, $increment, $tags, new DateTimeImmutable());
        $this->metrics[] = $metric;
        return $metric;
    }

    public function gauge(string $name, Tags $tags, float $value): Gauge {
        $metric = new Gauge($name, $value, $tags, new DateTimeImmutable());
        $this->metrics[] = $metric;
        return $metric;
    }

    public function histogram(string $name, Tags $tags, float $value): Histogram {
        $metric = new Histogram($name, $value, $tags, new DateTimeImmutable());
        $this->metrics[] = $metric;
        return $metric;
    }

    public function timer(string $name, Tags $tags, float $durationMs): Timer {
        $metric = new Timer($name, $durationMs, $tags, new DateTimeImmutable());
        $this->metrics[] = $metric;
        return $metric;
    }

    /** @return iterable<Metric> */
    public function all(): iterable {
        return $this->metrics;
    }

    /** @return iterable<Metric> */
    public function find(string $name, ?Tags $tags = null): iterable {
        foreach ($this->metrics as $metric) {
            if ($metric->name() !== $name) {
                continue;
            }
            if ($tags !== null && $metric->tags()->toKey() !== $tags->toKey()) {
                continue;
            }
            yield $metric;
        }
    }

    public function clear(): void {
        $this->metrics = [];
    }

    public function count(): int {
        return count($this->metrics);
    }
}
