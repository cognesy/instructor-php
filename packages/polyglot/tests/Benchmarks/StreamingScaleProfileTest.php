<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Streaming\InferenceStream;
use Cognesy\Polyglot\Tests\Support\FakeInferenceDriver;
use Cognesy\Utils\Profiler\ObjectCreationTrace;

/**
 * Scale profile for the Polyglot inference streaming layer.
 *
 * Streams deltas at 1K, 2K, 5K, 10K chunk counts through
 * FakeInferenceDriver -> InferenceStream and measures memory and wall-clock
 * time at each scale. Asserts linear growth characteristics.
 *
 * MEMORY MEASUREMENT: uses memory_get_usage(false), i.e. emalloc-tracked bytes.
 * The real-memory variant (true) is page-granular and reported a flat 0 growth
 * at every scale here, which made the memory assertions below vacuous -- a
 * per-delta allocation regression could not have failed them. Do not switch
 * these back to true.
 *
 * OBJECT COUNTS: InferenceUsage carries TracksObjectCreation, so
 * ObjectCreationTrace gives an exact per-stream construction count. That count
 * is the sharpest available regression gate for the streaming hot path: usage
 * data appears in roughly 1 chunk in 943 on a real stream, so a healthy adapter
 * constructs O(1) InferenceUsage objects per stream, not O(chunks).
 */

/**
 * @param callable(int, string): iterable<PartialInferenceDelta> $deltaFactory
 * @return array<string, mixed>
 */
function runInferenceStreamProfile(
    int $chunkCount,
    int $chunkSize = 64,
    ?callable $deltaFactory = null,
): array {
    $payload = '{"data":"' . str_repeat('x', $chunkSize - 11) . '"}';
    // Ensure chunk is exactly chunkSize bytes
    $payload = substr($payload, 0, $chunkSize);

    $deltaFactory ??= contentDeltaFactory(...);

    $driver = new FakeInferenceDriver(
        onStream: fn(): iterable => $deltaFactory($chunkCount, $payload),
    );

    $request = (new InferenceRequest())->with(options: ['stream' => true]);
    $stream = new InferenceStream(
        execution: InferenceExecution::fromRequest($request),
        driver: $driver,
        eventDispatcher: new EventDispatcher(),
    );

    gc_collect_cycles();
    ObjectCreationTrace::enable([InferenceUsage::class]);

    $memBefore = memory_get_usage(false);
    $peakBefore = memory_get_peak_usage(false);
    $timeBefore = hrtime(true);

    $received = 0;
    foreach ($stream->deltas() as $delta) {
        $received++;
    }

    $timeAfter = hrtime(true);
    $usageObjects = ObjectCreationTrace::createdCount(InferenceUsage::class);
    ObjectCreationTrace::reset();

    gc_collect_cycles();
    $memAfter = memory_get_usage(false);
    $peakAfter = memory_get_peak_usage(false);

    return [
        'chunks' => $chunkCount,
        'received' => $received,
        'mem_growth' => $memAfter - $memBefore,
        'peak_growth' => $peakAfter - $peakBefore,
        'time_ms' => ($timeAfter - $timeBefore) / 1_000_000,
        'payload_bytes' => $chunkCount * $chunkSize,
        'usage_objects' => $usageObjects,
    ];
}

/** @return iterable<PartialInferenceDelta> */
function contentDeltaFactory(int $chunkCount, string $payload): iterable {
    for ($i = 0; $i < $chunkCount - 1; $i++) {
        yield new PartialInferenceDelta(contentDelta: $payload);
    }
    yield new PartialInferenceDelta(contentDelta: $payload, finishReason: 'stop');
}

/**
 * Tool-call streaming shape: an id/name delta opening each call, followed by
 * argument fragments. Exercises InferenceStreamState::accumulateToolDelta()
 * and its tool-mutation bookkeeping, which the content-only profile never reaches.
 *
 * @return iterable<PartialInferenceDelta>
 */
function toolCallDeltaFactory(int $chunkCount, string $payload): iterable {
    $argFragment = substr($payload, 0, 16);
    // One tool call opened every 32 deltas; the rest are argument fragments.
    $callIndex = 0;
    for ($i = 0; $i < $chunkCount - 1; $i++) {
        if ($i % 32 === 0) {
            $callIndex++;
            yield new PartialInferenceDelta(
                toolId: 'call_' . $callIndex,
                toolName: 'do_something',
                toolArgs: '{"a":',
            );
            continue;
        }
        yield new PartialInferenceDelta(toolArgs: $argFragment);
    }
    yield new PartialInferenceDelta(toolArgs: '}', finishReason: 'tool_calls');
}

it('profiles inference streaming at 1K, 2K, 5K, 10K chunks', function () {
    $scales = [1_000, 2_000, 5_000, 10_000];
    $results = [];

    foreach ($scales as $count) {
        $results[$count] = runInferenceStreamProfile($count);
    }

    printScaleProfile('Inference Stream Scale Profile (content deltas)', $results);
    assertLinearScaleProfile($results);
});

it('profiles inference streaming of tool-call deltas at 1K, 2K, 5K, 10K chunks', function () {
    $scales = [1_000, 2_000, 5_000, 10_000];
    $results = [];

    foreach ($scales as $count) {
        $results[$count] = runInferenceStreamProfile($count, deltaFactory: toolCallDeltaFactory(...));
    }

    printScaleProfile('Inference Stream Scale Profile (tool-call deltas)', $results);
    assertLinearScaleProfile($results);
});

/** @param array<int, array<string, mixed>> $results */
function printScaleProfile(string $title, array $results): void {
    echo "\n\n  {$title}\n";
    echo "  ┌────────┬──────────┬─────────────┬─────────────┬───────────┬────────────┐\n";
    echo "  │ Chunks │  Payload │  Mem Growth │ Peak Growth │  Time ms  │ Usage objs │\n";
    echo "  ├────────┼──────────┼─────────────┼─────────────┼───────────┼────────────┤\n";
    foreach ($results as $r) {
        echo sprintf(
            "  │ %6s │ %8s │ %11s │ %11s │ %9s │ %10s │\n",
            number_format($r['chunks']),
            number_format($r['payload_bytes']),
            number_format($r['mem_growth']),
            number_format($r['peak_growth']),
            number_format($r['time_ms'], 1),
            number_format($r['usage_objects']),
        );
    }
    echo "  └────────┴──────────┴─────────────┴─────────────┴───────────┴────────────┘\n";
}

/** @param array<int, array<string, mixed>> $results */
function assertLinearScaleProfile(array $results): void {
    // All chunks must be received
    foreach ($results as $r) {
        expect($r['received'])->toBe($r['chunks']);
    }

    // The measurement must actually be able to see allocations. A flat zero here
    // means the profile has stopped being a regression gate -- fail loudly rather
    // than pass vacuously.
    expect($results[10_000]['mem_growth'])->toBeGreaterThan(0, sprintf(
        'Memory growth at 10K chunks measured as %s -- the profile is not observing '
        . 'allocations and cannot gate a regression.',
        number_format($results[10_000]['mem_growth']),
    ));

    // Memory growth must be sub-linear relative to chunk count.
    // Content string accumulates (linear with payload), but object overhead should not explode.
    // At 10K chunks * 64 bytes = 640KB payload, allow up to 8MB for string concat overhead.
    expect($results[10_000]['mem_growth'])->toBeLessThan(8 * 1024 * 1024, sprintf(
        'Memory grew by %s at 10K chunks — expected < 8 MB',
        number_format($results[10_000]['mem_growth']),
    ));

    // Growth ratio 10K/1K should be roughly linear (< 15x for 10x more chunks).
    // String concatenation in PHP has amortized overhead, so some super-linearity is expected.
    if ($results[1_000]['mem_growth'] > 0) {
        $ratio = $results[10_000]['mem_growth'] / $results[1_000]['mem_growth'];
        expect($ratio)->toBeLessThan(15.0, sprintf(
            'Memory ratio 10K/1K = %.1fx — expected < 15x (linear would be ~10x)',
            $ratio,
        ));
    }

    // Time at 10K should complete within 5 seconds
    expect($results[10_000]['time_ms'])->toBeLessThan(5_000, sprintf(
        '10K chunks took %.1f ms — expected < 5000 ms',
        $results[10_000]['time_ms'],
    ));

    // Time growth should be roughly linear: 10K/1K ratio < 15x
    if ($results[1_000]['time_ms'] > 0) {
        $timeRatio = $results[10_000]['time_ms'] / $results[1_000]['time_ms'];
        expect($timeRatio)->toBeLessThan(15.0, sprintf(
            'Time ratio 10K/1K = %.1fx — expected < 15x (linear would be ~10x)',
            $timeRatio,
        ));
    }
}
