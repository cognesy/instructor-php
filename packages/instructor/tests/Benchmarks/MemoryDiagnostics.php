<?php declare(strict_types=1);

namespace Cognesy\Instructor\Tests\Benchmarks;

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

/**
 * Memory Diagnostics - Lean memory profiling tool
 *
 * Quick diagnostics for memory consumption issues.
 * Run directly: php packages/instructor/tests/Benchmarks/MemoryDiagnostics.php
 *
 * Usage:
 *   php MemoryDiagnostics.php              # All tests
 *   php MemoryDiagnostics.php sync-stream  # Sync vs Stream only
 *   php MemoryDiagnostics.php layers       # Layer isolation only
 *   php MemoryDiagnostics.php pipeline     # Pipeline checkpoints
 */
final class MemoryDiagnostics
{
    private const PAYLOAD_SIZE = 10240; // 10KB

    public static function main(string $test = 'all'): void
    {
        echo "\n" . str_repeat('═', 70) . "\n";
        echo " MEMORY DIAGNOSTICS - Instructor Streaming\n";
        echo str_repeat('═', 70) . "\n\n";

        $tests = match($test) {
            'sync-stream', 'compare' => ['syncVsStream'],
            'layers', 'layer' => ['layerIsolation'],
            'pipeline', 'checkpoints' => ['pipelineCheckpoints'],
            default => ['syncVsStream', 'layerIsolation', 'pipelineCheckpoints'],
        };

        foreach ($tests as $testName) {
            self::$testName();
        }

        echo "\n" . str_repeat('═', 70) . "\n";
        echo " ✓ Diagnostics complete\n";
        echo str_repeat('═', 70) . "\n\n";
    }

    // TEST 1: Sync vs Stream Memory Comparison
    private static function syncVsStream(): void
    {
        echo "TEST 1: Sync vs Stream Memory Comparison\n";
        echo str_repeat('─', 70) . "\n\n";

        // Run sync test in isolation
        [$syncPeak, $syncObjects, $syncBaseline] = self::runSyncTest();
        gc_collect_cycles();

        // Run stream test in isolation
        [$streamPeak, $streamObjects, $streamBaseline] = self::runStreamTest();

        // Report
        printf("  Sync Memory:\n");
        printf("    Baseline:  %s\n", self::formatBytes($syncBaseline));
        printf("    Peak:      %s\n", self::formatBytes($syncPeak));
        printf("    Delta:     %s\n", self::formatBytes($syncPeak - $syncBaseline));
        printf("    Objects:   %d allocated\n", $syncObjects);

        printf("\n  Stream Memory:\n");
        printf("    Baseline:  %s\n", self::formatBytes($streamBaseline));
        printf("    Peak:      %s\n", self::formatBytes($streamPeak));
        printf("    Delta:     %s\n", self::formatBytes($streamPeak - $streamBaseline));
        printf("    Objects:   %d allocated\n", $streamObjects);

        $diff = $streamPeak - $syncPeak;
        $pct = $syncPeak > 0 ? ($diff / $syncPeak) * 100 : 0;

        printf("\n  Peak Difference: %s (%+.1f%%)\n\n", self::formatBytes(abs($diff)), $pct);

        if (abs($pct) < 10) {
            echo "  ✅ Stream and Sync use similar peak memory\n";
        } elseif ($diff > 0) {
            echo "  ⚠️  Stream uses MORE peak memory than Sync\n";
        } else {
            echo "  ✅ Stream uses LESS peak memory than Sync\n";
        }

        echo "\n";
    }

    private static function runSyncTest(): array
    {
        gc_collect_cycles();
        memory_reset_peak_usage();
        $baseline = memory_get_usage(false);
        $gcBefore = gc_status();

        $json = self::makeJson(self::PAYLOAD_SIZE);
        $driver = new FakeInferenceDriver(responses: [new InferenceResponse(content: $json)]);

        $so = (new StructuredOutput)
            ->withDriver($driver)
            ->withConfig((new StructuredOutputConfig())->with(responseIterator: 'modular'))
            ->with(messages: 'Test', responseModel: new Sequence(\stdClass::class), mode: OutputMode::Json);

        $result = $so->get();
        $peak = memory_get_peak_usage(false);
        $gcAfter = gc_status();
        $objects = $gcAfter['roots'] - $gcBefore['roots'];

        unset($so, $result, $driver, $json);

        return [$peak, $objects, $baseline];
    }

    private static function runStreamTest(): array
    {
        gc_collect_cycles();
        memory_reset_peak_usage();
        $baseline = memory_get_usage(false);
        $gcBefore = gc_status();

        $chunks = self::makeStream(self::PAYLOAD_SIZE);
        $driver = new FakeInferenceDriver(streamBatches: [$chunks]);

        $so = (new StructuredOutput)
            ->withDriver($driver)
            ->withConfig((new StructuredOutputConfig())->with(responseIterator: 'modular'))
            ->with(messages: 'Test', responseModel: new Sequence(\stdClass::class), mode: OutputMode::Json);

        $stream = $so->stream();
        $result = $stream->finalValue();
        $peak = memory_get_peak_usage(false);
        $gcAfter = gc_status();
        $objects = $gcAfter['roots'] - $gcBefore['roots'];

        unset($so, $result, $stream, $driver, $chunks);

        return [$peak, $objects, $baseline];
    }

    // TEST 2: Layer Isolation
    private static function layerIsolation(): void
    {
        echo "TEST 2: Layer Isolation (Which component uses memory?)\n";
        echo str_repeat('─', 70) . "\n\n";

        $chunks = self::makeStream(self::PAYLOAD_SIZE);

        // Layer 1: Chunks
        gc_collect_cycles();
        $before = memory_get_usage(false);
        $testChunks = self::makeStream(self::PAYLOAD_SIZE);
        $after = memory_get_usage(false);
        $layer1 = $after - $before;
        printf("  Layer 1 (Chunks):          %s\n", self::formatBytes($layer1));

        // Layer 2: Driver
        gc_collect_cycles();
        $before = memory_get_usage(false);
        $driver = new FakeInferenceDriver(streamBatches: [$chunks]);
        $after = memory_get_usage(false);
        $layer2 = $after - $before;
        printf("  Layer 2 (Driver):          %s\n", self::formatBytes($layer2));

        // Layer 3: Iteration
        $driver = new FakeInferenceDriver(streamBatches: [self::makeStream(self::PAYLOAD_SIZE)]);
        gc_collect_cycles();
        gc_disable(); // Prevent GC during measurement
        $before = memory_get_usage(false);
        $buffer = '';
        $request = new \Cognesy\Polyglot\Inference\Data\InferenceRequest();
        foreach ($driver->makeStreamResponsesFor($request) as $partial) {
            $buffer .= $partial->contentDelta;
        }
        $after = memory_get_usage(false);
        gc_enable();
        $layer3 = max(0, $after - $before); // Handle negative deltas from GC
        printf("  Layer 3 (Iteration):       %s\n", self::formatBytes($layer3));

        // Layer 4: Full pipeline
        $driver = new FakeInferenceDriver(streamBatches: [self::makeStream(self::PAYLOAD_SIZE)]);
        gc_collect_cycles();
        gc_disable(); // Prevent GC during measurement
        $before = memory_get_usage(false);

        $so = (new StructuredOutput)
            ->withDriver($driver)
            ->withConfig((new StructuredOutputConfig())->with(responseIterator: 'modular'))
            ->with(messages: 'Test', responseModel: new Sequence(\stdClass::class), mode: OutputMode::Json);

        $stream = $so->stream();
        $result = $stream->finalValue();
        $after = memory_get_usage(false);
        gc_enable();
        $layer4 = max(0, $after - $before); // Handle negative deltas from GC
        printf("  Layer 4 (Full Pipeline):   %s\n\n", self::formatBytes($layer4));

        // Analysis
        $instructorOverhead = max(0, $layer4 - $layer3);
        $pct = $layer4 > 0 ? ($instructorOverhead / $layer4) * 100 : 0;
        printf("  Instructor overhead:       %s (%.0f%%)\n\n",
            self::formatBytes(abs($instructorOverhead)),
            $pct
        );

        if ($instructorOverhead > $layer3 * 2) {
            echo "  ⚠️  Overhead primarily in Instructor module\n";
        } elseif ($layer3 > $layer2 * 2) {
            echo "  ⚠️  Overhead in stream iteration\n";
        } else {
            echo "  ✅ Memory distributed across layers\n";
        }

        echo "\n";
    }

    // TEST 3: Pipeline Checkpoints
    private static function pipelineCheckpoints(): void
    {
        echo "TEST 3: Pipeline Memory Checkpoints\n";
        echo str_repeat('─', 70) . "\n\n";

        gc_collect_cycles();
        $baseline = memory_get_usage(false);
        echo "  Baseline:                  " . self::formatBytes($baseline) . "\n";

        $chunks = self::makeStream(self::PAYLOAD_SIZE);
        gc_collect_cycles();
        echo "  After chunks:              " . self::formatDelta($baseline) . "\n";

        $driver = new FakeInferenceDriver(streamBatches: [$chunks]);
        gc_collect_cycles();
        echo "  After driver:              " . self::formatDelta($baseline) . "\n";

        $so = new StructuredOutput();
        gc_collect_cycles();
        echo "  After StructuredOutput:    " . self::formatDelta($baseline) . "\n";

        $so = $so->withDriver($driver);
        gc_collect_cycles();
        echo "  After withDriver():        " . self::formatDelta($baseline) . "\n";

        $so = $so->withConfig((new StructuredOutputConfig())->with(responseIterator: 'modular'));
        gc_collect_cycles();
        echo "  After withConfig():        " . self::formatDelta($baseline) . "\n";

        $so = $so->with(messages: 'Test', responseModel: new Sequence(\stdClass::class), mode: OutputMode::Json);
        gc_collect_cycles();
        echo "  After with():              " . self::formatDelta($baseline) . "\n";

        $stream = $so->stream();
        gc_collect_cycles();
        echo "  After stream():            " . self::formatDelta($baseline) . "\n";

        $result = $stream->finalValue();
        gc_collect_cycles();
        $final = memory_get_usage(false);
        echo "  After finalValue():        " . self::formatDelta($baseline) . "\n";

        printf("\n  Total:                     %s\n", self::formatBytes($final - $baseline));
        printf("  Peak:                      %s\n", self::formatBytes(memory_get_peak_usage(false)));

        echo "\n";
    }

    // Helpers
    private static function formatBytes(int $bytes): string {
        if ($bytes >= 1048576) return sprintf('%.2f MB', $bytes / 1048576);
        if ($bytes >= 1024) return sprintf('%.2f KB', $bytes / 1024);
        return sprintf('%d B', $bytes);
    }

    private static function formatDelta(int $baseline): string {
        $current = memory_get_usage(false);
        $delta = $current - $baseline;
        return self::formatBytes(abs($delta));
    }

    private static function makeStream(int $targetBytes): array {
        $open = '{"list":[';
        $close = ']}';
        $items = [];
        $size = strlen($open) + strlen($close);
        $i = 0;

        while ($size < $targetBytes) {
            $txt = str_repeat('x', 16);
            $item = sprintf('{"i":%d,"t":"%s"}', $i, $txt);
            $sep = ($i === 0) ? '' : ',';
            $items[] = $sep . $item;
            $size += strlen($sep . $item);
            $i++;
        }

        $chunks = [];
        $chunks[] = new PartialInferenceResponse(contentDelta: $open);
        foreach ($items as $piece) {
            $chunks[] = new PartialInferenceResponse(contentDelta: $piece);
        }
        $chunks[] = new PartialInferenceResponse(contentDelta: $close, finishReason: 'stop');

        return $chunks;
    }

    private static function makeJson(int $targetBytes): string {
        $content = '{"list":[';
        $i = 0;

        while (strlen($content) < $targetBytes) {
            $txt = str_repeat('x', 16);
            $piece = sprintf('%s{"i":%d,"t":"%s"}', $i === 0 ? '' : ',', $i, $txt);
            $content .= $piece;
            $i++;
        }

        return $content . ']}';
    }
}

// Run if executed directly
if (basename($argv[0] ?? '') === 'MemoryDiagnostics.php') {
    require_once __DIR__ . '/../../../../vendor/autoload.php';
    MemoryDiagnostics::main($argv[1] ?? 'all');
}
