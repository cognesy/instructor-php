<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Streaming\InferenceStream;
use Cognesy\Polyglot\Tests\Support\FakeInferenceDriver;

/**
 * Scale profile for the Polyglot inference streaming layer.
 *
 * Streams JSON content deltas at 1K, 2K, 5K, 10K chunk counts through
 * FakeInferenceDriver -> InferenceStream and measures peak memory and
 * wall-clock time at each scale. Asserts linear growth characteristics.
 */

function runInferenceStreamProfile(int $chunkCount, int $chunkSize = 64): array {
    $payload = '{"data":"' . str_repeat('x', $chunkSize - 11) . '"}';
    // Ensure chunk is exactly chunkSize bytes
    $payload = substr($payload, 0, $chunkSize);

    $driver = new FakeInferenceDriver(
        onStream: function () use ($chunkCount, $payload): iterable {
            for ($i = 0; $i < $chunkCount - 1; $i++) {
                yield new PartialInferenceDelta(contentDelta: $payload);
            }
            yield new PartialInferenceDelta(contentDelta: $payload, finishReason: 'stop');
        },
    );

    $request = (new InferenceRequest())->with(options: ['stream' => true]);
    $stream = new InferenceStream(
        execution: InferenceExecution::fromRequest($request),
        driver: $driver,
        eventDispatcher: new EventDispatcher(),
    );

    gc_collect_cycles();
    $memBefore = memory_get_usage(true);
    $peakBefore = memory_get_peak_usage(true);
    $timeBefore = hrtime(true);

    $received = 0;
    foreach ($stream->deltas() as $delta) {
        $received++;
    }

    $timeAfter = hrtime(true);
    gc_collect_cycles();
    $memAfter = memory_get_usage(true);
    $peakAfter = memory_get_peak_usage(true);

    return [
        'chunks' => $chunkCount,
        'received' => $received,
        'mem_growth' => $memAfter - $memBefore,
        'peak_growth' => $peakAfter - $peakBefore,
        'time_ms' => ($timeAfter - $timeBefore) / 1_000_000,
        'payload_bytes' => $chunkCount * $chunkSize,
    ];
}

it('profiles inference streaming at 1K, 2K, 5K, 10K chunks', function () {
    $scales = [1_000, 2_000, 5_000, 10_000];
    $results = [];

    foreach ($scales as $count) {
        $results[$count] = runInferenceStreamProfile($count);
    }

    // Print results table
    echo "\n\n  Inference Stream Scale Profile\n";
    echo "  ┌────────┬──────────┬─────────────┬─────────────┬───────────┐\n";
    echo "  │ Chunks │  Payload │  Mem Growth  │ Peak Growth │  Time ms  │\n";
    echo "  ├────────┼──────────┼─────────────┼─────────────┼───────────┤\n";
    foreach ($results as $r) {
        echo sprintf(
            "  │ %6s │ %8s │ %11s │ %11s │ %9s │\n",
            number_format($r['chunks']),
            number_format($r['payload_bytes']),
            number_format($r['mem_growth']),
            number_format($r['peak_growth']),
            number_format($r['time_ms'], 1),
        );
    }
    echo "  └────────┴──────────┴─────────────┴─────────────┴───────────┘\n";

    // All chunks must be received
    foreach ($results as $r) {
        expect($r['received'])->toBe($r['chunks']);
    }

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
});
