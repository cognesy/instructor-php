<?php declare(strict_types=1);

namespace Cognesy\Utils\TagMap\Tags;

use Cognesy\Utils\TagMap\Contracts\TagInterface;

/**
 * Pure timing tag - captures essential timing data only.
 * 
 * No memory tracking, no complex logic - just clean timing information.
 * Dedicated consumer components handle SLA monitoring, alerts, etc.
 *
 * Example usage:
 * ```php
 * $timings = $state->allTags(TimingTag::class);
 * foreach ($timings as $timing) {
 *     echo "{$timing->operationName}: {$timing->durationMs()}ms\n";
 * }
 * ```
 */
readonly class TimeProfilerTag implements TagInterface
{
    public function __construct(
        public float $startTime,
        public float $endTime,
        public float $duration,
        public ?string $operationName = null,
        public bool $success = true,
    ) {}

    /**
     * Get duration in milliseconds for easier readability.
     */
    public function durationMs(): float {
        return $this->duration * 1000;
    }

    /**
     * Get duration in microseconds for high precision needs.
     */
    public function durationMicros(): float {
        return $this->duration * 1_000_000;
    }

    /**
     * Get formatted duration string with appropriate unit.
     */
    public function durationFormatted(): string {
        $ms = $this->durationMs();
        if ($ms < 1) {
            return number_format($this->durationMicros(), 0) . 'μs';
        }
        if ($ms < 1000) {
            return number_format($ms, 2) . 'ms';
        }
        return number_format($this->duration, 3) . 's';
    }

    /**
     * Get start time as DateTime object.
     */
    public function startDateTime(): \DateTimeImmutable {
        $timestamp = sprintf('%.6F', $this->startTime);
        return \DateTimeImmutable::createFromFormat('U.u', $timestamp)
            ?: (\DateTimeImmutable::createFromFormat('U', (string)(int)$this->startTime) ?: new \DateTimeImmutable('@' . (int)$this->startTime));
    }

    /**
     * Get end time as DateTime object.
     */
    public function endDateTime(): \DateTimeImmutable {
        $timestamp = sprintf('%.6F', $this->endTime);
        return \DateTimeImmutable::createFromFormat('U.u', $timestamp)
            ?: (\DateTimeImmutable::createFromFormat('U', (string)(int)$this->endTime) ?: new \DateTimeImmutable('@' . (int)$this->endTime));
    }

    /**
     * Check if this timing represents a successful operation.
     */
    public function isSuccess(): bool {
        return $this->success;
    }

    /**
     * Check if this timing represents a failed operation.
     */
    public function isFailure(): bool {
        return !$this->success;
    }

    /**
     * Convert to array for serialization/logging.
     */
    public function toArray(): array {
        return [
            'operation_name' => $this->operationName,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'duration_seconds' => $this->duration,
            'duration_ms' => $this->durationMs(),
            'success' => $this->success,
            'formatted_duration' => $this->durationFormatted(),
        ];
    }
}