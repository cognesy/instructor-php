<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Tag;

/**
 * Tag that records timing information for pipeline operations.
 *
 * Captures high-precision timing data including start time, end time,
 * and calculated duration. Supports optional operation naming and
 * success/failure tracking.
 *
 * Example usage:
 * ```php
 * // Get timing information from computation
 * $timings = $computation->all(TimingTag::class);
 *
 * foreach ($timings as $timing) {
 *     echo "{$timing->operationName}: {$timing->durationMs()}ms\n";
 * }
 *
 * // Get total processing time
 * $totalTime = array_sum(array_map(fn($t) => $t->duration, $timings));
 * ```
 */
readonly class TimingTag implements TagInterface
{
    public function __construct(
        public float $startTime,
        public float $endTime,
        public float $duration,
        public ?string $operationName = null,
        public bool $success = true,
        public ?string $error = null,
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
            ?: \DateTimeImmutable::createFromFormat('U', (string)(int)$this->startTime);
    }

    /**
     * Get end time as DateTime object.
     */
    public function endDateTime(): \DateTimeImmutable {
        $timestamp = sprintf('%.6F', $this->endTime);
        return \DateTimeImmutable::createFromFormat('U.u', $timestamp)
            ?: \DateTimeImmutable::createFromFormat('U', (string)(int)$this->endTime);
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
     * Get a summary string for logging/debugging.
     */
    public function summary(): string {
        $name = $this->operationName ?? 'operation';
        $status = $this->success ? 'SUCCESS' : 'FAILED';
        $duration = $this->durationFormatted();

        $summary = "{$name}: {$status} in {$duration}";

        if ($this->error) {
            $summary .= " (Error: {$this->error})";
        }

        return $summary;
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
            'error' => $this->error,
            'formatted_duration' => $this->durationFormatted(),
        ];
    }

    /**
     * Create a timing tag for a successful operation.
     */
    public static function success(
        float $startTime,
        float $endTime,
        ?string $operationName = null,
    ): self {
        return new self(
            startTime: $startTime,
            endTime: $endTime,
            duration: $endTime - $startTime,
            operationName: $operationName,
            success: true,
        );
    }

    /**
     * Create a timing tag for a failed operation.
     */
    public static function failure(
        float $startTime,
        float $endTime,
        string $error,
        ?string $operationName = null,
    ): self {
        return new self(
            startTime: $startTime,
            endTime: $endTime,
            duration: $endTime - $startTime,
            operationName: $operationName,
            success: false,
            error: $error,
        );
    }
}