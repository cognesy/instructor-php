<?php declare(strict_types=1);

namespace Cognesy\Metrics\Data;

use DateTimeImmutable;
use JsonSerializable;
use Stringable;

/**
 * Base interface for all metric types.
 *
 * Metrics are immutable value objects representing a single
 * recorded measurement with dimensional tags and a timestamp.
 */
interface Metric extends JsonSerializable, Stringable
{
    /**
     * The metric name (e.g., "inference.duration_ms").
     */
    public function name(): string;

    /**
     * The recorded value.
     */
    public function value(): float;

    /**
     * Dimensional tags for filtering and grouping.
     */
    public function tags(): Tags;

    /**
     * When the metric was recorded.
     */
    public function timestamp(): DateTimeImmutable;

    /**
     * The metric type identifier (counter, gauge, histogram, timer).
     */
    public function type(): string;

    /**
     * Convert to array representation.
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
