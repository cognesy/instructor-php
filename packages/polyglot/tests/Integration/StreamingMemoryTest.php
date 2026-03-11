<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Streaming\InferenceStream;
use Cognesy\Polyglot\Tests\Support\FakeInferenceDriver;

/**
 * Memory profile for the Polyglot inference streaming layer.
 *
 * Streams 1 000 deltas through FakeInferenceDriver → InferenceStream
 * and asserts that peak memory stays within a fixed ceiling.
 * The result can be extrapolated linearly to 10K / 100K deltas.
 */

it('streams large response (~10K tokens) with bounded memory', function () {
    // Simulate a single large JSON response: ~40KB split into ~400 deltas of 100 bytes each
    $totalBytes = 40_000;
    $chunkSize  = 100;
    $chunkCount = (int) ceil($totalBytes / $chunkSize);
    $payload    = str_repeat('a', $chunkSize);

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
    $memBefore = memory_get_usage(false);

    $received = 0;
    foreach ($stream->deltas() as $delta) {
        $received++;
    }

    gc_collect_cycles();
    $memAfter = memory_get_usage(false);

    $growth = $memAfter - $memBefore;

    expect($received)->toBe($chunkCount);

    // InferenceStreamState accumulates the full content string (~40KB).
    // PHP string concatenation causes temporary allocations (~6.5x observed).
    // Allow 8x payload for string concat overhead + framework objects.
    expect($growth)->toBeLessThan($totalBytes * 8, sprintf(
        'Memory grew by %s streaming a %s response (expected < %s).',
        number_format($growth),
        number_format($totalBytes),
        number_format($totalBytes * 8),
    ));

    echo sprintf(
        "\n  [polyglot-large] %s payload, %d deltas | growth=%s | overhead ratio=%.2f%%",
        number_format($totalBytes),
        $chunkCount,
        number_format($growth),
        $totalBytes > 0 ? ($growth / $totalBytes) * 100 : 0,
    );
});

it('streams 1000 inference deltas with bounded memory', function () {
    $deltaCount = 1_000;
    $chunkBody  = str_repeat('a', 128);

    // Build deltas — use a generator to avoid holding them all in memory twice
    $driver = new FakeInferenceDriver(
        onStream: function () use ($deltaCount, $chunkBody): iterable {
            for ($i = 0; $i < $deltaCount - 1; $i++) {
                yield new PartialInferenceDelta(contentDelta: $chunkBody);
            }
            yield new PartialInferenceDelta(contentDelta: $chunkBody, finishReason: 'stop');
        },
    );

    $request = (new InferenceRequest())->with(options: ['stream' => true]);
    $stream = new InferenceStream(
        execution: InferenceExecution::fromRequest($request),
        driver: $driver,
        eventDispatcher: new EventDispatcher(),
    );

    // Baseline
    gc_collect_cycles();
    $memBefore = memory_get_usage(false);

    $received = 0;
    foreach ($stream->deltas() as $delta) {
        $received++;
    }

    gc_collect_cycles();
    $memAfter = memory_get_usage(false);

    $growth = $memAfter - $memBefore;

    expect($received)->toBe($deltaCount);

    // InferenceStreamState accumulates content string (grows with N),
    // but object count should stay constant. Allow 1 MB ceiling for
    // 1000 × 128-byte deltas (128 KB of content + framework overhead).
    expect($growth)->toBeLessThan(1024 * 1024, sprintf(
        'Memory grew by %s during inference streaming (expected < 1 MB). '
        . 'This suggests objects or buffers are accumulating beyond the content string.',
        number_format($growth),
    ));

    // Report for extrapolation
    $perDelta = $deltaCount > 0 ? $growth / $deltaCount : 0;
    echo sprintf(
        "\n  [polyglot] %d deltas | growth=%s | per-delta=%.1f bytes | 10K≈%s | 100K≈%s",
        $deltaCount,
        number_format($growth),
        $perDelta,
        number_format($perDelta * 10_000),
        number_format($perDelta * 100_000),
    );
});

it('accumulates content proportional to payload, not to object count', function () {
    $chunkBody = str_repeat('b', 64);

    // Run with 100 deltas, then 500, and verify growth is linear with content, not quadratic
    $growths = [];

    foreach ([100, 500] as $count) {
        $driver = new FakeInferenceDriver(
            onStream: function () use ($count, $chunkBody): iterable {
                for ($i = 0; $i < $count - 1; $i++) {
                    yield new PartialInferenceDelta(contentDelta: $chunkBody);
                }
                yield new PartialInferenceDelta(contentDelta: $chunkBody, finishReason: 'stop');
            },
        );

        $request = (new InferenceRequest())->with(options: ['stream' => true]);
        $stream = new InferenceStream(
            execution: InferenceExecution::fromRequest($request),
            driver: $driver,
            eventDispatcher: new EventDispatcher(),
        );

        gc_collect_cycles();
        $before = memory_get_usage(false);

        foreach ($stream->deltas() as $_) {}

        gc_collect_cycles();
        $after = memory_get_usage(false);

        $growths[$count] = $after - $before;
    }

    // If growth is proportional to content, the ratio should be close to 5x (500/100).
    // If it's quadratic (object leak), it would be ~25x. Allow up to 8x.
    $ratio = $growths[100] > 0 ? $growths[500] / $growths[100] : 0;

    expect($ratio)->toBeLessThan(8.0, sprintf(
        'Memory growth ratio 500/100 deltas = %.1fx (expected < 8x). '
        . 'This suggests super-linear (quadratic) memory growth — likely an object leak. '
        . '100=%s, 500=%s',
        $ratio,
        number_format($growths[100]),
        number_format($growths[500]),
    ));

    echo sprintf(
        "\n  [polyglot-linearity] 100=%s, 500=%s, ratio=%.2fx",
        number_format($growths[100]),
        number_format($growths[500]),
        $ratio,
    );
});
