<?php declare(strict_types=1);

namespace Cognesy\Metrics\Data;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Timer - duration measurement.
 *
 * Timers are specialized histograms for tracking durations.
 * They provide convenience methods for duration conversion
 * and throughput calculation.
 */
final readonly class Timer implements Metric
{
    public function __construct(
        private string $name,
        private float $durationMs,
        private Tags $tags,
        private DateTimeImmutable $timestamp,
    ) {
        if ($durationMs < 0) {
            throw new InvalidArgumentException('Timer duration must be non-negative');
        }
    }

    public static function create(
        string $name,
        float $durationMs,
        array $tags = [],
        ?DateTimeImmutable $timestamp = null,
    ): self {
        return new self(
            $name,
            $durationMs,
            Tags::of($tags),
            $timestamp ?? new DateTimeImmutable(),
        );
    }

    public function name(): string {
        return $this->name;
    }

    /**
     * Duration in milliseconds (primary value).
     */
    public function value(): float {
        return $this->durationMs;
    }

    /**
     * Alias for value() - duration in milliseconds.
     */
    public function durationMs(): float {
        return $this->durationMs;
    }

    /**
     * Duration in seconds.
     */
    public function durationSeconds(): float {
        return $this->durationMs / 1000;
    }

    public function tags(): Tags {
        return $this->tags;
    }

    public function timestamp(): DateTimeImmutable {
        return $this->timestamp;
    }

    public function type(): string {
        return 'timer';
    }

    public function toArray(): array {
        return [
            'type' => $this->type(),
            'name' => $this->name,
            'value' => $this->durationMs,
            'duration_ms' => $this->durationMs,
            'duration_seconds' => $this->durationSeconds(),
            'tags' => $this->tags->toArray(),
            'timestamp' => $this->timestamp->format('c'),
        ];
    }

    public function jsonSerialize(): array {
        return $this->toArray();
    }

    public function __toString(): string {
        $tagsStr = (string) $this->tags;
        $tagsFormatted = $tagsStr !== '' ? "{{$tagsStr}}" : '';
        return sprintf('%s%s %gms', $this->name, $tagsFormatted, $this->durationMs);
    }
}
