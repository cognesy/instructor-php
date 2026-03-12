<?php declare(strict_types=1);

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;

/**
 * Scale profile for the Instructor structured-output streaming layer.
 *
 * Simulates realistic LLM API streaming: the full JSON is split into
 * small token-sized chunks (~20 chars each), matching how real APIs
 * deliver content. The chunk count parameter controls total chunks
 * streamed, NOT the number of items in the sequence.
 *
 * At ~20 chars/chunk and ~30 chars/item, 1K chunks ≈ 650 items,
 * 10K chunks ≈ 6500 items.
 */

class ScaleProfileItem
{
    public int $id = 0;
    public string $name = '';
}

/**
 * Build the full JSON for a sequence, then split into token-sized chunks.
 * @return array{0: string[], 1: int} [chunks, itemCount]
 */
function buildRealisticChunks(int $chunkCount, int $chunkSize = 20): array {
    $targetBytes = $chunkCount * $chunkSize;
    $id = 0;
    $json = '{"list":[';

    while (strlen($json) < $targetBytes) {
        $id++;
        $comma = $id > 1 ? ',' : '';
        $json .= sprintf('%s{"id":%d,"name":"item-%d"}', $comma, $id, $id);
    }
    $json .= ']}';

    return [str_split($json, $chunkSize), $id];
}

function runStructuredOutputProfile(int $chunkCount, int $materializationInterval = 1): array {
    [$chunks, $expectedItems] = buildRealisticChunks($chunkCount);
    $actualChunks = count($chunks);

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

    $config = new StructuredOutputConfig(
        streamMaterializationInterval: $materializationInterval,
    );

    $stream = (new StructuredOutput())
        ->withRuntime(makeStructuredRuntime(
            driver: $driver,
            outputMode: OutputMode::Json,
            config: $config,
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
        'chunks' => $actualChunks,
        'items' => $expectedItems,
        'received' => $received,
        'mem_growth' => $memAfter - $memBefore,
        'peak_growth' => $peakAfter - $peakBefore,
        'time_ms' => ($timeAfter - $timeBefore) / 1_000_000,
    ];
}

function printProfileTable(string $title, array $results): void {
    echo "\n\n  $title\n";
    echo "  ┌────────┬───────┬──────────┬─────────────┬─────────────┬───────────┐\n";
    echo "  │ Chunks │ Items │ Received │  Mem Growth  │ Peak Growth │  Time ms  │\n";
    echo "  ├────────┼───────┼──────────┼─────────────┼─────────────┼───────────┤\n";
    foreach ($results as $r) {
        echo sprintf(
            "  │ %6s │ %5s │ %8s │ %11s │ %11s │ %9s │\n",
            number_format($r['chunks']),
            number_format($r['items']),
            number_format($r['received']),
            number_format($r['mem_growth']),
            number_format($r['peak_growth']),
            number_format($r['time_ms'], 1),
        );
    }
    echo "  └────────┴───────┴──────────┴─────────────┴─────────────┴───────────┘\n";
}

// ── Baseline: materializationInterval=1 (every delta) ───────────────

it('profiles structured output streaming at 1K, 2K, 5K, 10K chunks', function () {
    $scales = [1_000, 2_000, 5_000, 10_000];
    $results = [];

    foreach ($scales as $count) {
        $results[$count] = runStructuredOutputProfile($count);
    }

    printProfileTable('StructuredOutput Stream Scale Profile (N=1, every delta)', $results);

    if ($results[10_000]['received'] > 0) {
        echo sprintf(
            "\n  At 10K: %d items, %.3f ms/chunk\n",
            $results[10_000]['received'],
            $results[10_000]['time_ms'] / $results[10_000]['chunks'],
        );
    }

    foreach ($results as $r) {
        expect($r['received'])->toBe($r['items']);
    }

    expect($results[10_000]['mem_growth'])->toBeLessThan(16 * 1024 * 1024);

    if ($results[1_000]['mem_growth'] > 0) {
        $ratio = $results[10_000]['mem_growth'] / $results[1_000]['mem_growth'];
        expect($ratio)->toBeLessThan(15.0);
    }

    expect($results[10_000]['time_ms'])->toBeLessThan(60_000);

    if ($results[1_000]['time_ms'] > 0) {
        $timeRatio = $results[10_000]['time_ms'] / $results[1_000]['time_ms'];
        expect($timeRatio)->toBeLessThan(50.0);
    }
});

// ── Throttled: materializationInterval=4 ────────────────────────────

it('profiles structured output streaming with materializationInterval=4', function () {
    $interval = 4;
    $scales = [1_000, 2_000, 5_000, 10_000];
    $results = [];

    foreach ($scales as $count) {
        $results[$count] = runStructuredOutputProfile($count, $interval);
    }

    printProfileTable("StructuredOutput Stream Scale Profile (N=$interval, throttled)", $results);

    if ($results[10_000]['received'] > 0) {
        echo sprintf(
            "\n  At 10K: %d items, %.3f ms/chunk\n",
            $results[10_000]['received'],
            $results[10_000]['time_ms'] / $results[10_000]['chunks'],
        );
    }

    // All items must still be received — throttle must not lose data
    foreach ($results as $r) {
        expect($r['received'])->toBe($r['items']);
    }

    // Memory ceiling unchanged
    expect($results[10_000]['mem_growth'])->toBeLessThan(16 * 1024 * 1024);

    // Time at 10K should be well under 60s
    expect($results[10_000]['time_ms'])->toBeLessThan(60_000);

    // Throttled should be meaningfully faster than baseline (at least 1.5x at 10K)
    $baseline = runStructuredOutputProfile(10_000, 1);
    $speedup = $baseline['time_ms'] / $results[10_000]['time_ms'];
    echo sprintf("\n  Speedup vs N=1 at 10K: %.1fx\n", $speedup);

    expect($speedup)->toBeGreaterThan(1.5, sprintf(
        'N=%d speedup = %.1fx — expected > 1.5x',
        $interval,
        $speedup,
    ));
});
