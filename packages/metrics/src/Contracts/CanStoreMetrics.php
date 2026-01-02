<?php declare(strict_types=1);

namespace Cognesy\Metrics\Contracts;

use Cognesy\Metrics\Data\Counter;
use Cognesy\Metrics\Data\Gauge;
use Cognesy\Metrics\Data\Histogram;
use Cognesy\Metrics\Data\Metric;
use Cognesy\Metrics\Data\Tags;
use Cognesy\Metrics\Data\Timer;

/**
 * Registry that stores metrics.
 */
interface CanStoreMetrics
{
    public function counter(string $name, Tags $tags, float $increment = 1): Counter;

    public function gauge(string $name, Tags $tags, float $value): Gauge;

    public function histogram(string $name, Tags $tags, float $value): Histogram;

    public function timer(string $name, Tags $tags, float $durationMs): Timer;

    /** @return iterable<Metric> */
    public function all(): iterable;

    /** @return iterable<Metric> */
    public function find(string $name, ?Tags $tags = null): iterable;

    public function clear(): void;

    public function count(): int;
}
