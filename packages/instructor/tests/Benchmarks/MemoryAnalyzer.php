<?php declare(strict_types=1);

/**
 * Memory Analyzer - Deep Memory Profiling Tool
 *
 * Standalone script for analyzing memory usage of streaming vs sync processing.
 * Provides detailed metrics that PHPBench doesn't expose.
 *
 * ## What This Measures
 *
 * 1. Peak memory usage (real allocated pages, not PHP internal)
 * 2. Memory delta during operation
 * 3. Object count (if available)
 * 4. Memory overhead ratio (memory / payload size)
 * 5. Memory efficiency comparison
 *
 * ## Usage
 *
 * Run directly:
 *   php packages/instructor/tests/Benchmarks/MemoryAnalyzer.php
 *
 * With specific test:
 *   php packages/instructor/tests/Benchmarks/MemoryAnalyzer.php 10KB
 *
 * ## Output Format
 *
 * CSV-style output for easy analysis:
 * Driver,Mode,Payload,MemBefore,MemAfter,MemDelta,PeakBefore,PeakAfter,PeakDelta,OverheadRatio
 *
 * ## Production Readiness Indicators
 *
 * ✅ Good: Overhead ratio < 5x payload size
 * ⚠️  Warning: Overhead ratio 5-10x payload size
 * ❌ Critical: Overhead ratio > 10x payload size
 *
 * ✅ Good: Stream peak < Sync peak for large payloads
 * ❌ Bad: Stream peak > Sync peak (streaming not working)
 *
 * ✅ Good: Linear memory growth with payload size
 * ❌ Bad: Exponential memory growth (memory leak)
 */

require_once __DIR__ . '/../../../../vendor/autoload.php';

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

final class MemoryAnalyzer
{
    private array $results = [];

    public function run(string $filter = 'all'): void
    {
        echo "Memory Analysis Tool\n";
        echo str_repeat('=', 80) . "\n\n";

        $tests = [
            '1KB' => 1024,
            '10KB' => 10240,
            '100KB' => 102400,
        ];

        if ($filter !== 'all' && isset($tests[$filter])) {
            $tests = [$filter => $tests[$filter]];
        }

        // CSV Header
        echo "Driver,Mode,Payload,MemBefore,MemAfter,MemDelta,PeakBefore,PeakAfter,PeakDelta,OverheadRatio\n";

        foreach ($tests as $label => $size) {
            $this->testPayloadSize($label, $size);
        }

        echo "\n" . str_repeat('=', 80) . "\n";
        $this->printSummary();
    }

    private function testPayloadSize(string $label, int $size): void
    {
        $drivers = ['modular' => 'Clean', 'legacy' => 'Legacy', 'partials' => 'Partials'];

        foreach ($drivers as $driver => $driverLabel) {
            // Test streaming
            $streamStats = $this->measureStream($driver, $size);
            $this->printStats($driverLabel, 'Stream', $label, $streamStats);
            $this->results[] = array_merge(['driver' => $driverLabel, 'mode' => 'Stream', 'payload' => $label], $streamStats);

            // Test sync
            $syncStats = $this->measureSync($driver, $size);
            $this->printStats($driverLabel, 'Sync', $label, $syncStats);
            $this->results[] = array_merge(['driver' => $driverLabel, 'mode' => 'Sync', 'payload' => $label], $syncStats);
        }
    }

    private function measureStream(string $driver, int $payloadSize): array
    {
        $chunks = $this->makeStream($payloadSize);
        $fakeDriver = new FakeInferenceDriver(streamBatches: [$chunks]);

        // Clean baseline
        gc_collect_cycles();
        $memBefore = memory_get_usage(true);
        $peakBefore = memory_get_peak_usage(true);

        $so = (new StructuredOutput)
            ->withDriver($fakeDriver)
            ->withConfig((new StructuredOutputConfig())->with(responseIterator: $driver))
            ->with(
                messages: 'Test',
                responseModel: new Sequence(\stdClass::class),
                mode: OutputMode::Json,
            );

        $stream = $so->stream();
        $result = $stream->finalValue();

        $memAfter = memory_get_usage(true);
        $peakAfter = memory_get_peak_usage(true);

        return [
            'mem_before' => $memBefore,
            'mem_after' => $memAfter,
            'mem_delta' => $memAfter - $memBefore,
            'peak_before' => $peakBefore,
            'peak_after' => $peakAfter,
            'peak_delta' => $peakAfter - $peakBefore,
            'overhead_ratio' => ($peakAfter - $peakBefore) / $payloadSize,
        ];
    }

    private function measureSync(string $driver, int $payloadSize): array
    {
        $json = $this->makeJson($payloadSize);
        $fakeDriver = new FakeInferenceDriver(responses: [new InferenceResponse(content: $json)]);

        // Clean baseline
        gc_collect_cycles();
        $memBefore = memory_get_usage(true);
        $peakBefore = memory_get_peak_usage(true);

        $so = (new StructuredOutput)
            ->withDriver($fakeDriver)
            ->withConfig((new StructuredOutputConfig())->with(responseIterator: $driver))
            ->with(
                messages: 'Test',
                responseModel: new Sequence(\stdClass::class),
                mode: OutputMode::Json,
            );

        $result = $so->get();

        $memAfter = memory_get_usage(true);
        $peakAfter = memory_get_peak_usage(true);

        return [
            'mem_before' => $memBefore,
            'mem_after' => $memAfter,
            'mem_delta' => $memAfter - $memBefore,
            'peak_before' => $peakBefore,
            'peak_after' => $peakAfter,
            'peak_delta' => $peakAfter - $peakBefore,
            'overhead_ratio' => ($peakAfter - $peakBefore) / $payloadSize,
        ];
    }

    private function printStats(string $driver, string $mode, string $payload, array $stats): void
    {
        printf(
            "%s,%s,%s,%d,%d,%d,%d,%d,%d,%.2f\n",
            $driver,
            $mode,
            $payload,
            $stats['mem_before'],
            $stats['mem_after'],
            $stats['mem_delta'],
            $stats['peak_before'],
            $stats['peak_after'],
            $stats['peak_delta'],
            $stats['overhead_ratio']
        );
    }

    private function printSummary(): void
    {
        echo "\nMemory Efficiency Analysis:\n\n";

        // Group by payload size and find stream vs sync differences
        $byPayload = [];
        foreach ($this->results as $result) {
            $key = $result['payload'];
            if (!isset($byPayload[$key])) {
                $byPayload[$key] = [];
            }
            $byPayload[$key][] = $result;
        }

        foreach ($byPayload as $payload => $results) {
            echo "Payload: $payload\n";

            // Find Clean driver results
            $cleanStream = null;
            $cleanSync = null;
            foreach ($results as $r) {
                if ($r['driver'] === 'Clean' && $r['mode'] === 'Stream') {
                    $cleanStream = $r;
                }
                if ($r['driver'] === 'Clean' && $r['mode'] === 'Sync') {
                    $cleanSync = $r;
                }
            }

            if ($cleanStream && $cleanSync) {
                $streamPeak = $cleanStream['peak_delta'];
                $syncPeak = $cleanSync['peak_delta'];
                $difference = $streamPeak - $syncPeak;
                $percentDiff = ($difference / $syncPeak) * 100;

                printf("  Stream peak: %s\n", $this->formatBytes($streamPeak));
                printf("  Sync peak:   %s\n", $this->formatBytes($syncPeak));
                printf("  Difference:  %s (%.1f%%)\n", $this->formatBytes(abs($difference)), abs($percentDiff));

                if ($streamPeak < $syncPeak) {
                    echo "  ✅ Stream uses less memory\n";
                } else if ($percentDiff < 10) {
                    echo "  ⚠️  Stream and Sync memory usage similar\n";
                } else {
                    echo "  ❌ Stream uses MORE memory - potential issue!\n";
                }

                // Check overhead ratio
                $avgOverhead = ($cleanStream['overhead_ratio'] + $cleanSync['overhead_ratio']) / 2;
                printf("  Overhead ratio: %.1fx\n", $avgOverhead);
                if ($avgOverhead < 5) {
                    echo "  ✅ Good memory efficiency\n";
                } else if ($avgOverhead < 10) {
                    echo "  ⚠️  Moderate overhead\n";
                } else {
                    echo "  ❌ High memory overhead - optimization needed!\n";
                }
            }
            echo "\n";
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return sprintf('%.2fMB', $bytes / 1048576);
        } elseif ($bytes >= 1024) {
            return sprintf('%.2fKB', $bytes / 1024);
        }
        return sprintf('%dB', $bytes);
    }

    private function makeStream(int $targetBytes): array
    {
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

    private function makeJson(int $targetBytes): string
    {
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
if (basename($argv[0] ?? '') === 'MemoryAnalyzer.php') {
    $filter = $argv[1] ?? 'all';
    (new MemoryAnalyzer())->run($filter);
}
