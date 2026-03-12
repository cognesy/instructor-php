<?php declare(strict_types=1);

/**
 * Polyglot inference streaming benchmarks.
 *
 * ## Coverage
 *
 * Raw inference streaming layer — no deserialization, just delta transport.
 * Measures the overhead of InferenceStream processing at various scales.
 *
 * ## Known failure modes guarded against
 *
 * - Immutable object accumulation (memory explosion from copying state)
 * - Content string concatenation overhead (amortized but can spike)
 * - Event dispatcher overhead scaling non-linearly with chunk count
 *
 * ## How to run
 *
 *   composer bench -- --filter=PolyglotStreamBench
 *   composer bench -- --filter=benchStream10K
 */

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Streaming\InferenceStream;
use Cognesy\Polyglot\Tests\Support\FakeInferenceDriver;

require_once __DIR__ . '/../Support/FakeInferenceDriver.php';

final class PolyglotStreamBench
{
    private function makeDriver(int $chunkCount, int $chunkSize = 64): FakeInferenceDriver {
        $payload = '{"data":"' . str_repeat('x', max(0, $chunkSize - 11)) . '"}';
        $payload = substr($payload, 0, $chunkSize);

        return new FakeInferenceDriver(
            onStream: function () use ($chunkCount, $payload): iterable {
                for ($i = 0; $i < $chunkCount - 1; $i++) {
                    yield new PartialInferenceDelta(contentDelta: $payload);
                }
                yield new PartialInferenceDelta(contentDelta: $payload, finishReason: 'stop');
            },
        );
    }

    private function runStream(int $chunkCount, int $chunkSize = 64): int {
        $driver = $this->makeDriver($chunkCount, $chunkSize);
        $request = (new InferenceRequest())->with(options: ['stream' => true]);
        $stream = new InferenceStream(
            execution: InferenceExecution::fromRequest($request),
            driver: $driver,
            eventDispatcher: new EventDispatcher(),
        );

        $received = 0;
        foreach ($stream->deltas() as $delta) {
            $received++;
        }
        return $received;
    }

    // ========================================================================
    // Small payload chunks (64 bytes) — measures object/event overhead
    // ========================================================================

    /**
     * @Revs(100)
     * @Iterations(3)
     * @OutputTimeUnit("milliseconds", precision=3)
     * @Groups({"stream", "small"})
     */
    public function benchStream100Chunks(): void {
        $n = $this->runStream(100);
        assert($n === 100);
    }

    /**
     * @Revs(50)
     * @Iterations(3)
     * @OutputTimeUnit("milliseconds", precision=3)
     * @Groups({"stream", "small"})
     */
    public function benchStream1KChunks(): void {
        $n = $this->runStream(1_000);
        assert($n === 1_000);
    }

    /**
     * @Revs(10)
     * @Iterations(3)
     * @OutputTimeUnit("milliseconds", precision=3)
     * @Groups({"stream", "medium"})
     */
    public function benchStream5KChunks(): void {
        $n = $this->runStream(5_000);
        assert($n === 5_000);
    }

    /**
     * @Revs(5)
     * @Iterations(3)
     * @OutputTimeUnit("milliseconds", precision=3)
     * @Groups({"stream", "large"})
     */
    public function benchStream10KChunks(): void {
        $n = $this->runStream(10_000);
        assert($n === 10_000);
    }

    // ========================================================================
    // Large payload chunks (512 bytes) — measures string accumulation cost
    // ========================================================================

    /**
     * @Revs(50)
     * @Iterations(3)
     * @OutputTimeUnit("milliseconds", precision=3)
     * @Groups({"stream", "large-payload"})
     */
    public function benchStream1KLargeChunks(): void {
        $n = $this->runStream(1_000, 512);
        assert($n === 1_000);
    }

    /**
     * @Revs(5)
     * @Iterations(3)
     * @OutputTimeUnit("milliseconds", precision=3)
     * @Groups({"stream", "large-payload"})
     */
    public function benchStream10KLargeChunks(): void {
        $n = $this->runStream(10_000, 512);
        assert($n === 10_000);
    }
}
