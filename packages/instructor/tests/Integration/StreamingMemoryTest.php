<?php declare(strict_types=1);

use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;

/**
 * Memory profile for the Instructor structured-output streaming layer.
 *
 * Streams a sequence of 1 000 items through FakeInferenceDriver → StructuredOutput
 * and asserts that peak memory stays within a fixed ceiling.
 * The result can be extrapolated linearly to 10K / 100K items.
 */

it('streams large single object (~10K tokens) with bounded memory', function () {
    // Build a single large JSON object matching LargeObjectTestItem schema.
    // Uses long string values to simulate ~40KB response.
    $fullJson = json_encode([
        'title' => str_repeat('Title word ', 500),       // ~5.5KB
        'summary' => str_repeat('Summary text here. ', 800), // ~15KB
        'content' => str_repeat('Content body data. ', 1000), // ~19KB
    ]);
    $totalBytes = strlen($fullJson);

    // Split into ~100-byte chunks (simulating token-by-token streaming)
    $chunkSize = 100;
    $chunks = str_split($fullJson, $chunkSize);
    $chunkCount = count($chunks);

    $driver = new FakeInferenceDriver(
        onStream: function () use ($chunks): iterable {
            $last = count($chunks) - 1;
            foreach ($chunks as $i => $chunk) {
                yield new PartialInferenceDelta(
                    contentDelta: $chunk,
                    finishReason: $i === $last ? 'stop' : '',
                );
            }
        },
    );

    $stream = (new StructuredOutput())
        ->withRuntime(makeStructuredRuntime(
            driver: $driver,
            outputMode: OutputMode::Json,
        ))
        ->with(
            messages: 'Extract data.',
            responseModel: LargeObjectTestItem::class,
        )
        ->withStreaming(true)
        ->stream();

    gc_collect_cycles();
    $memBefore = memory_get_usage(false);

    $partialCount = 0;
    foreach ($stream->partials() as $partial) {
        $partialCount++;
    }

    gc_collect_cycles();
    $memAfter = memory_get_usage(false);

    $growth = $memAfter - $memBefore;

    expect($partialCount)->toBeGreaterThan(0);

    // Full pipeline carries a meaningful fixed runtime cost on top of payload growth.
    // Keep a fixed 3 MB budget for framework/runtime overhead plus 8x payload growth.
    $memoryCeiling = (3 * 1024 * 1024) + ($totalBytes * 8);
    expect($growth)->toBeLessThan($memoryCeiling, sprintf(
        'Memory grew by %s streaming a %s single-object response through full pipeline '
        . '(expected < %s). Partials received: %d.',
        number_format($growth),
        number_format($totalBytes),
        number_format($memoryCeiling),
        $partialCount,
    ));

    echo sprintf(
        "\n  [instructor-large] %s payload, %d chunks, %d partials | growth=%s | overhead=%.1fx",
        number_format($totalBytes),
        $chunkCount,
        $partialCount,
        number_format($growth),
        $totalBytes > 0 ? $growth / $totalBytes : 0,
    );
});

it('streams 1000-item sequence with bounded memory', function () {
    $itemCount = 1_000;

    // Build a JSON stream that yields items one at a time.
    // Each chunk adds one complete item to the sequence.
    $driver = new FakeInferenceDriver(
        onStream: function () use ($itemCount): iterable {
            // Opening
            yield new PartialInferenceDelta(contentDelta: '{"list":[');

            for ($i = 1; $i <= $itemCount; $i++) {
                $comma = $i > 1 ? ',' : '';
                yield new PartialInferenceDelta(
                    contentDelta: sprintf('%s{"id":%d,"name":"item-%d"}', $comma, $i, $i),
                );
            }

            // Closing
            yield new PartialInferenceDelta(contentDelta: ']}', finishReason: 'stop');
        },
    );

    $stream = (new StructuredOutput())
        ->withRuntime(makeStructuredRuntime(
            driver: $driver,
            outputMode: OutputMode::Json,
        ))
        ->with(
            messages: 'Extract the list.',
            responseModel: Sequence::of(MemoryTestItem::class),
        )
        ->withStreaming(true)
        ->stream();

    // Baseline
    gc_collect_cycles();
    $memBefore = memory_get_usage(false);

    $received = 0;
    foreach ($stream->sequence() as $item) {
        $received++;
    }

    gc_collect_cycles();
    $memAfter = memory_get_usage(false);

    $growth = $memAfter - $memBefore;

    expect($received)->toBe($itemCount);

    // Sequence accumulates all items (by design), so memory grows with N.
    // Each item is ~small object. 1000 items should stay well under 4 MB.
    expect($growth)->toBeLessThan(4 * 1024 * 1024, sprintf(
        'Memory grew by %s during sequence streaming (expected < 4 MB for %d items). '
        . 'This suggests excessive intermediate object allocation.',
        number_format($growth),
        $itemCount,
    ));

    // Report for extrapolation
    $perItem = $itemCount > 0 ? $growth / $itemCount : 0;
    echo sprintf(
        "\n  [instructor] %d items | growth=%s | per-item=%.0f bytes | 10K≈%s | 100K≈%s",
        $itemCount,
        number_format($growth),
        $perItem,
        number_format($perItem * 10_000),
        number_format($perItem * 100_000),
    );
});

it('sequence memory grows linearly with item count', function () {
    $growths = [];

    foreach ([50, 250] as $count) {
        $driver = new FakeInferenceDriver(
            onStream: function () use ($count): iterable {
                yield new PartialInferenceDelta(contentDelta: '{"list":[');
                for ($i = 1; $i <= $count; $i++) {
                    $comma = $i > 1 ? ',' : '';
                    yield new PartialInferenceDelta(
                        contentDelta: sprintf('%s{"id":%d,"name":"item-%d"}', $comma, $i, $i),
                    );
                }
                yield new PartialInferenceDelta(contentDelta: ']}', finishReason: 'stop');
            },
        );

        $stream = (new StructuredOutput())
            ->withRuntime(makeStructuredRuntime(
                driver: $driver,
                outputMode: OutputMode::Json,
            ))
            ->with(
                messages: 'Extract the list.',
                responseModel: Sequence::of(MemoryTestItem::class),
            )
            ->withStreaming(true)
            ->stream();

        gc_collect_cycles();
        $before = memory_get_usage(false);

        foreach ($stream->sequence() as $_) {}

        gc_collect_cycles();
        $after = memory_get_usage(false);

        $growths[$count] = $after - $before;
    }

    // Linear growth: ratio should be close to 5x (250/50). Allow up to 8x.
    $ratio = $growths[50] > 0 ? $growths[250] / $growths[50] : 0;

    expect($ratio)->toBeLessThan(8.0, sprintf(
        'Memory growth ratio 250/50 items = %.1fx (expected < 8x). '
        . 'This suggests super-linear memory growth. '
        . '50=%s, 250=%s',
        $ratio,
        number_format($growths[50]),
        number_format($growths[250]),
    ));

    echo sprintf(
        "\n  [instructor-linearity] 50=%s, 250=%s, ratio=%.2fx",
        number_format($growths[50]),
        number_format($growths[250]),
        $ratio,
    );
});

class LargeObjectTestItem
{
    public string $title = '';
    public string $summary = '';
    public string $content = '';
}

class MemoryTestItem
{
    public int $id;
    public string $name;
}
