<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Core;

/**
 * Measures elapsed wall-independent time for inference lifecycle durations.
 *
 * Uses a monotonic nanosecond clock (hrtime) so durations stay correct beyond
 * 60 seconds and are immune to system clock adjustments. The clock reader is
 * injectable purely so tests can simulate long/precise intervals deterministically
 * without sleeping; production always uses hrtime.
 */
final class MonotonicStopwatch
{
    /** @var callable(): int Monotonic clock reader returning nanoseconds. */
    private $nanoReader;

    private ?int $startedAtNs = null;

    /**
     * @param (callable(): int)|null $nanoReader
     */
    public function __construct(?callable $nanoReader = null) {
        $this->nanoReader = $nanoReader ?? static fn(): int => hrtime(true);
    }

    public function start(): void {
        $this->startedAtNs = ($this->nanoReader)();
    }

    public function isRunning(): bool {
        return $this->startedAtNs !== null;
    }

    /**
     * Total elapsed milliseconds since start() as a non-negative float.
     * Returns 0.0 if the stopwatch has not been started.
     */
    public function elapsedMs(): float {
        if ($this->startedAtNs === null) {
            return 0.0;
        }

        $elapsedNs = ($this->nanoReader)() - $this->startedAtNs;
        if ($elapsedNs <= 0) {
            return 0.0;
        }

        return $elapsedNs / 1_000_000;
    }
}
