<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Tag;

use Cognesy\Utils\TagMap\Contracts\TagInterface;

/**
 * Pure step-level timing tag for granular performance measurement.
 *
 * Captures timing data for individual pipeline steps without any
 * additional logic. Consumer components handle analysis and actions.
 */
readonly class StepTimingTag implements TagInterface
{
    public function __construct(
        public string $stepName,
        public float $startTime,
        public float $endTime,
        public float $duration,
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
        $result = \DateTimeImmutable::createFromFormat('U.u', $timestamp);
        if ($result === false) {
            $result = \DateTimeImmutable::createFromFormat('U', (string)(int)$this->startTime);
        }
        return $result ?: new \DateTimeImmutable();
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
            'step_name' => $this->stepName,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'duration_seconds' => $this->duration,
            'duration_ms' => $this->durationMs(),
            'success' => $this->success,
            'formatted_duration' => $this->durationFormatted(),
        ];
    }
}