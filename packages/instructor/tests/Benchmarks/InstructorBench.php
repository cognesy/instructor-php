<?php declare(strict_types=1);

/**
 * Comprehensive Instructor benchmarks covering known failure modes.
 *
 * ## Coverage
 *
 * 1. Sync: object, array of objects, Sequence — baseline deserialization cost
 * 2. Streaming object (partials) — catches flat-object re-parsing overhead
 * 3. Streaming Sequence (sequence) — catches O(n²) re-deserialization regression
 * 4. Streaming Sequence with throttle (N=4) — validates materialization interval
 *
 * ## Known failure modes guarded against
 *
 * - Sequence::fromArray() re-deserializing ALL items on every delta (O(n²))
 * - Flat-object partials() re-parsing entire JSON on every chunk
 * - Memory explosion from immutable object accumulation
 *
 * ## How to run
 *
 *   composer bench -- --filter=InstructorBench
 *   composer bench -- --filter=benchStreamSequence
 *   composer bench -- --report=aggregate
 */

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Instructor\Enums\OutputMode;

require_once __DIR__ . '/../Support/FakeInferenceDriver.php';
require_once __DIR__ . '/BenchModels.php';

final class InstructorBench
{
    // ========================================================================
    // Data generators
    // ========================================================================

    /** Build JSON for a single object, padded to target size */
    private function makeObjectJson(int $targetBytes): string {
        $base = [
            'fullName' => 'Dr. Jonathan Doe',
            'age' => 42,
            'bio' => '',
            'email' => 'jdoe@example.com',
            'phone' => '+1-555-0142',
        ];
        $baseJson = json_encode($base, JSON_THROW_ON_ERROR);
        $remaining = max(0, $targetBytes - strlen($baseJson));
        $base['bio'] = str_repeat('A seasoned engineer. ', (int) ceil($remaining / 21));
        $base['bio'] = substr($base['bio'], 0, $remaining);
        return json_encode($base, JSON_THROW_ON_ERROR);
    }

    /** Build JSON for an array of objects */
    private function makeArrayJson(int $itemCount): string {
        $items = [];
        for ($i = 1; $i <= $itemCount; $i++) {
            $items[] = ['id' => $i, 'name' => "item-$i"];
        }
        return json_encode($items, JSON_THROW_ON_ERROR);
    }

    /** Build JSON for a Sequence */
    private function makeSequenceJson(int $itemCount): string {
        $items = [];
        for ($i = 1; $i <= $itemCount; $i++) {
            $items[] = ['id' => $i, 'name' => "item-$i"];
        }
        return json_encode(['list' => $items], JSON_THROW_ON_ERROR);
    }

    /** Split JSON into token-sized chunks (~20 chars each) */
    private function tokenize(string $json, int $chunkSize = 20): array {
        return str_split($json, $chunkSize);
    }

    /** Create a streaming FakeInferenceDriver from pre-tokenized chunks */
    private function streamDriver(array $chunks): FakeInferenceDriver {
        return new FakeInferenceDriver(
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
    }

    /** Create a sync FakeInferenceDriver from JSON */
    private function syncDriver(string $json): FakeInferenceDriver {
        return new FakeInferenceDriver(
            responses: [new InferenceResponse(content: $json)],
        );
    }

    // ========================================================================
    // SYNC: Single object — scaling with JSON size
    // ========================================================================

    /**
     * @Revs(100)
     * @Iterations(3)
     * @OutputTimeUnit("milliseconds", precision=3)
     * @Groups({"sync", "object"})
     */
    public function benchSyncObject128B(): void {
        $json = $this->makeObjectJson(128);
        $driver = $this->syncDriver($json);
        $result = (new StructuredOutput)
            ->withRuntime(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Json))
            ->with(messages: 'Extract profile.', responseModel: BenchProfile::class)
            ->get();
        assert($result->fullName !== '');
    }

    /**
     * @Revs(50)
     * @Iterations(3)
     * @OutputTimeUnit("milliseconds", precision=3)
     * @Groups({"sync", "object"})
     */
    public function benchSyncObject1KB(): void {
        $json = $this->makeObjectJson(1024);
        $driver = $this->syncDriver($json);
        $result = (new StructuredOutput)
            ->withRuntime(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Json))
            ->with(messages: 'Extract profile.', responseModel: BenchProfile::class)
            ->get();
        assert($result->fullName !== '');
    }

    /**
     * @Revs(10)
     * @Iterations(3)
     * @OutputTimeUnit("milliseconds", precision=3)
     * @Groups({"sync", "object"})
     */
    public function benchSyncObject10KB(): void {
        $json = $this->makeObjectJson(10240);
        $driver = $this->syncDriver($json);
        $result = (new StructuredOutput)
            ->withRuntime(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Json))
            ->with(messages: 'Extract profile.', responseModel: BenchProfile::class)
            ->get();
        assert($result->fullName !== '');
    }

    /**
     * @Revs(5)
     * @Iterations(3)
     * @OutputTimeUnit("milliseconds", precision=3)
     * @Groups({"sync", "object"})
     */
    public function benchSyncObject50KB(): void {
        $json = $this->makeObjectJson(51200);
        $driver = $this->syncDriver($json);
        $result = (new StructuredOutput)
            ->withRuntime(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Json))
            ->with(messages: 'Extract profile.', responseModel: BenchProfile::class)
            ->get();
        assert($result->fullName !== '');
    }

    /**
     * @Revs(3)
     * @Iterations(3)
     * @OutputTimeUnit("milliseconds", precision=3)
     * @Groups({"sync", "object"})
     */
    public function benchSyncObject100KB(): void {
        $json = $this->makeObjectJson(102400);
        $driver = $this->syncDriver($json);
        $result = (new StructuredOutput)
            ->withRuntime(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Json))
            ->with(messages: 'Extract profile.', responseModel: BenchProfile::class)
            ->get();
        assert($result->fullName !== '');
    }

    // ========================================================================
    // SYNC: Sequence
    // ========================================================================

    /**
     * @Revs(50)
     * @Iterations(3)
     * @OutputTimeUnit("milliseconds", precision=3)
     * @Groups({"sync", "sequence"})
     */
    public function benchSyncSequence100Items(): void {
        $json = $this->makeSequenceJson(100);
        $driver = $this->syncDriver($json);
        $result = (new StructuredOutput)
            ->withRuntime(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Json))
            ->with(messages: 'Extract list.', responseModel: Sequence::of(BenchItem::class))
            ->get();
        assert($result->count() === 100);
    }

    /**
     * @Revs(10)
     * @Iterations(3)
     * @OutputTimeUnit("milliseconds", precision=3)
     * @Groups({"sync", "sequence"})
     */
    public function benchSyncSequence1000Items(): void {
        $json = $this->makeSequenceJson(1000);
        $driver = $this->syncDriver($json);
        $result = (new StructuredOutput)
            ->withRuntime(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Json))
            ->with(messages: 'Extract list.', responseModel: Sequence::of(BenchItem::class))
            ->get();
        assert($result->count() === 1000);
    }

    // ========================================================================
    // STREAMING: Single object via partials()
    // Guards against: flat-object re-parsing cost growing with JSON size
    // ========================================================================

    /**
     * @Revs(5)
     * @Iterations(3)
     * @OutputTimeUnit("milliseconds", precision=3)
     * @Groups({"stream", "object", "partials"})
     */
    public function benchStreamObjectPartials1K(): void {
        $json = $this->makeObjectJson(1024);
        $chunks = $this->tokenize($json);
        $driver = $this->streamDriver($chunks);
        $stream = (new StructuredOutput())
            ->withRuntime(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Json))
            ->with(messages: 'Extract profile.', responseModel: BenchProfile::class)
            ->withStreaming(true)
            ->stream();
        $count = 0;
        foreach ($stream->partials() as $partial) {
            $count++;
        }
        assert($count > 0);
    }

    /**
     * @Revs(1)
     * @Iterations(3)
     * @OutputTimeUnit("milliseconds", precision=3)
     * @Groups({"stream", "object", "partials"})
     */
    public function benchStreamObjectPartials10K(): void {
        $json = $this->makeObjectJson(10240);
        $chunks = $this->tokenize($json);
        $driver = $this->streamDriver($chunks);
        $stream = (new StructuredOutput())
            ->withRuntime(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Json))
            ->with(messages: 'Extract profile.', responseModel: BenchProfile::class)
            ->withStreaming(true)
            ->stream();
        $count = 0;
        foreach ($stream->partials() as $partial) {
            $count++;
        }
        assert($count > 0);
    }

    // ========================================================================
    // STREAMING: Sequence via sequence()
    // Guards against: O(n²) re-deserialization of all items on each delta
    // ========================================================================

    /**
     * @Revs(5)
     * @Iterations(3)
     * @OutputTimeUnit("milliseconds", precision=3)
     * @Groups({"stream", "sequence"})
     */
    public function benchStreamSequence1KChunks(): void {
        $json = $this->makeSequenceJson(650);
        $chunks = $this->tokenize($json);
        $driver = $this->streamDriver($chunks);
        $stream = (new StructuredOutput())
            ->withRuntime(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Json))
            ->with(messages: 'Extract list.', responseModel: Sequence::of(BenchItem::class))
            ->withStreaming(true)
            ->stream();
        $received = 0;
        foreach ($stream->sequence() as $item) {
            $received++;
        }
        assert($received === 650);
    }

    /**
     * @Revs(1)
     * @Iterations(3)
     * @OutputTimeUnit("milliseconds", precision=3)
     * @Groups({"stream", "sequence"})
     */
    public function benchStreamSequence5KChunks(): void {
        $json = $this->makeSequenceJson(3300);
        $chunks = $this->tokenize($json);
        $driver = $this->streamDriver($chunks);
        $stream = (new StructuredOutput())
            ->withRuntime(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Json))
            ->with(messages: 'Extract list.', responseModel: Sequence::of(BenchItem::class))
            ->withStreaming(true)
            ->stream();
        $received = 0;
        foreach ($stream->sequence() as $item) {
            $received++;
        }
        assert($received === 3300);
    }

    // ========================================================================
    // STREAMING: Sequence with materialization throttle (N=4)
    // Validates that throttle reduces CPU cost without losing items
    // ========================================================================

    /**
     * @Revs(5)
     * @Iterations(3)
     * @OutputTimeUnit("milliseconds", precision=3)
     * @Groups({"stream", "sequence", "throttle"})
     */
    public function benchStreamSequenceThrottled1KChunks(): void {
        $json = $this->makeSequenceJson(650);
        $chunks = $this->tokenize($json);
        $driver = $this->streamDriver($chunks);
        $config = new StructuredOutputConfig(
            streamMaterializationInterval: 4,
        );
        $stream = (new StructuredOutput())
            ->withRuntime(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Json, config: $config))
            ->with(messages: 'Extract list.', responseModel: Sequence::of(BenchItem::class))
            ->withStreaming(true)
            ->stream();
        $received = 0;
        foreach ($stream->sequence() as $item) {
            $received++;
        }
        assert($received === 650);
    }

    /**
     * @Revs(1)
     * @Iterations(3)
     * @OutputTimeUnit("milliseconds", precision=3)
     * @Groups({"stream", "sequence", "throttle"})
     */
    public function benchStreamSequenceThrottled5KChunks(): void {
        $json = $this->makeSequenceJson(3300);
        $chunks = $this->tokenize($json);
        $driver = $this->streamDriver($chunks);
        $config = new StructuredOutputConfig(
            streamMaterializationInterval: 4,
        );
        $stream = (new StructuredOutput())
            ->withRuntime(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Json, config: $config))
            ->with(messages: 'Extract list.', responseModel: Sequence::of(BenchItem::class))
            ->withStreaming(true)
            ->stream();
        $received = 0;
        foreach ($stream->sequence() as $item) {
            $received++;
        }
        assert($received === 3300);
    }
}
