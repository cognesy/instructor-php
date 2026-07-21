<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Core\MonotonicStopwatch;

/**
 * A scripted monotonic clock: returns the next queued nanosecond reading on each
 * call, holding the last value once the script is exhausted. Lets us simulate
 * long/precise intervals without sleeping.
 *
 * @param list<int> $nanoReadings
 * @return callable(): int
 */
function scriptedNanoClock(array $nanoReadings): callable
{
    $index = 0;
    return function () use (&$index, $nanoReadings): int {
        $value = $nanoReadings[$index] ?? end($nanoReadings);
        if ($index < count($nanoReadings) - 1) {
            $index++;
        }
        return (int) $value;
    };
}

it('returns 0.0 before it is started', function () {
    $sw = new MonotonicStopwatch(scriptedNanoClock([0]));
    expect($sw->isRunning())->toBeFalse()
        ->and($sw->elapsedMs())->toBe(0.0);
});

it('reports total elapsed milliseconds for a 61-second interval without wrapping', function () {
    // start reads 0ns, elapsedMs reads 61s in ns
    $sw = new MonotonicStopwatch(scriptedNanoClock([0, 61_000_000_000]));
    $sw->start();

    expect($sw->isRunning())->toBeTrue()
        ->and($sw->elapsedMs())->toBe(61_000.0);
});

it('reports multi-minute durations without wrapping every 60 seconds', function () {
    // 3 minutes 45 seconds = 225s
    $sw = new MonotonicStopwatch(scriptedNanoClock([1_000, 1_000 + 225_000_000_000]));
    $sw->start();

    expect($sw->elapsedMs())->toBe(225_000.0);
});

it('preserves sub-second precision', function () {
    // 1.5 ms = 1_500_000 ns
    $sw = new MonotonicStopwatch(scriptedNanoClock([0, 1_500_000]));
    $sw->start();

    expect($sw->elapsedMs())->toBe(1.5);
});

it('never returns a negative duration even if the clock goes backwards', function () {
    $sw = new MonotonicStopwatch(scriptedNanoClock([5_000_000_000, 1_000_000_000]));
    $sw->start();

    expect($sw->elapsedMs())->toBe(0.0);
});
