<?php declare(strict_types=1);

use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;

/**
 * Scale profile for the Instructor structured-output streaming layer.
 *
 * Streams a sequence of items at 1K, 2K, 5K, 10K chunk counts through
 * FakeInferenceDriver -> StructuredOutput and measures peak memory and
 * wall-clock time at each scale. Asserts linear growth characteristics.
 */

class ScaleProfileItem
{
    public int $id = 0;
    public string $name = '';
}

function runStructuredOutputProfile(int $chunkCount): array {
    // Each chunk adds one complete item to the sequence JSON
    $driver = new FakeInferenceDriver(
        onStream: function () use ($chunkCount): iterable {
            yield new PartialInferenceDelta(contentDelta: '{"list":[');
            for ($i = 1; $i <= $chunkCount; $i++) {
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
            messages: 'Extract list.',
            responseModel: Sequence::of(ScaleProfileItem::class),
        )
        ->withStreaming(true)
        ->stream();

    gc_collect_cycles();
    $memBefore = memory_get_usage(true);
    $peakBefore = memory_get_peak_usage(true);
    $timeBefore = hrtime(true);

    $received = 0;
    foreach ($stream->sequence() as $item) {
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
    ];
}

it('profiles structured output streaming at 1K, 2K, 5K, 10K items', function () {
    $scales = [1_000, 2_000, 5_000, 10_000];
    $results = [];

    foreach ($scales as $count) {
        $results[$count] = runStructuredOutputProfile($count);
    }

    // Print results table
    echo "\n\n  StructuredOutput Stream Scale Profile\n";
    echo "  ┌────────┬──────────┬─────────────┬─────────────┬───────────┐\n";
    echo "  │  Items │ Received │  Mem Growth  │ Peak Growth │  Time ms  │\n";
    echo "  ├────────┼──────────┼─────────────┼─────────────┼───────────┤\n";
    foreach ($results as $r) {
        echo sprintf(
            "  │ %6s │ %8s │ %11s │ %11s │ %9s │\n",
            number_format($r['chunks']),
            number_format($r['received']),
            number_format($r['mem_growth']),
            number_format($r['peak_growth']),
            number_format($r['time_ms'], 1),
        );
    }
    echo "  └────────┴──────────┴─────────────┴─────────────┴───────────┘\n";

    // Per-item stats
    if ($results[10_000]['received'] > 0) {
        $perItem = $results[10_000]['mem_growth'] / $results[10_000]['received'];
        $timePerItem = $results[10_000]['time_ms'] / $results[10_000]['received'];
        echo sprintf(
            "\n  At 10K: %.0f bytes/item, %.3f ms/item\n",
            $perItem,
            $timePerItem,
        );
    }

    // All items must be received
    foreach ($results as $r) {
        expect($r['received'])->toBe($r['chunks']);
    }

    // Sequence accumulates all items (by design), so memory grows with N.
    // At 10K items, each ~small object, allow up to 16 MB.
    expect($results[10_000]['mem_growth'])->toBeLessThan(16 * 1024 * 1024, sprintf(
        'Memory grew by %s at 10K items — expected < 16 MB',
        number_format($results[10_000]['mem_growth']),
    ));

    // Growth ratio 10K/1K should be roughly linear (< 15x for 10x more items).
    if ($results[1_000]['mem_growth'] > 0) {
        $ratio = $results[10_000]['mem_growth'] / $results[1_000]['mem_growth'];
        expect($ratio)->toBeLessThan(15.0, sprintf(
            'Memory ratio 10K/1K = %.1fx — expected < 15x (linear would be ~10x)',
            $ratio,
        ));
    }

    // Time at 10K should complete within 120 seconds
    // (full pipeline: JSON parsing, deserialization, validation per item)
    expect($results[10_000]['time_ms'])->toBeLessThan(120_000, sprintf(
        '10K items took %.1f ms — expected < 120s',
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
