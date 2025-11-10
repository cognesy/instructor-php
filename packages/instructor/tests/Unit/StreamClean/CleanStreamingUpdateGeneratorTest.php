<?php declare(strict_types=1);

// NOTE: These tests are currently skipped due to PendingInference mocking limitations.
// PendingInference is a concrete class, not an interface, making it difficult to mock properly.
// Recommendation: Move to Feature tests using FakeInferenceDriver or refactor for better testability.

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Core\Inference;
use Cognesy\Instructor\Core\InferenceProvider;
use Cognesy\Instructor\Creation\ResponseModelFactory;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Enums\AttemptPhase;
use Cognesy\Instructor\ResponseIterators\Clean\CleanStreamFactory;
use Cognesy\Instructor\ResponseIterators\Clean\CleanStreamingUpdateGenerator;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Instructor\Validation\Contracts\CanValidatePartialResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Schema\Factories\SchemaFactory;
use Cognesy\Schema\Factories\ToolCallBuilder;
use Cognesy\Utils\Result\Result;

class TestGeneratorModel {
    public string $test = '';
}

function makeGeneratorTestResponseModel(): ResponseModel {
    $cfg = new StructuredOutputConfig();
    $schemaFactory = new SchemaFactory(useObjectReferences: $cfg->useObjectReferences());
    $factory = new ResponseModelFactory(new ToolCallBuilder($schemaFactory), $schemaFactory, $cfg, new EventDispatcher());
    return $factory->fromAny(TestGeneratorModel::class);
}

function makeInferenceProvider(array $chunks): InferenceProvider {
    $mockProvider = new class($chunks) {
        public function __construct(public array $chunks) {}
    };

    $mockPending = new class($mockProvider->chunks) {
        public function __construct(private array $chunks) {}

        public function stream() {
            return $this;
        }

        public function responses(): Generator {
            foreach ($this->chunks as $chunk) {
                yield $chunk;
            }
        }

        public function toInferenceResponse() {
            throw new \RuntimeException('Not implemented');
        }
    };

    return new class($mockPending) extends InferenceProvider {
        public function __construct(private $mockPending) {}

        #[\Override]
        public function getInference(StructuredOutputExecution $execution) {
            return $this->mockPending;
        }
    };
}

function makeCleanFactory(): CleanStreamFactory {
    $deserializer = new class implements CanDeserializeResponse {
        public function deserialize(string $json, ResponseModel $responseModel): Result {
            $data = json_decode($json, true);
            return Result::success((object) $data);
        }
    };

    $validator = new class implements CanValidatePartialResponse {
        public function validatePartialResponse(string $json, ResponseModel $responseModel): Result {
            return Result::success(null);
        }
    };

    $transformer = new class implements CanTransformResponse {
        public function transform(mixed $object, ResponseModel $responseModel): Result {
            return Result::success($object);
        }
    };

    $events = new class implements CanHandleEvents {
        public function dispatch(object $event): object { return $event; }
        public function wiretap(callable $callable): void {}
        public function getListenersForEvent(object $event): iterable { return []; }
        public function addListener(string $name, callable $listener, int $priority = 0): void {}
    };

    return new CleanStreamFactory($deserializer, $validator, $transformer, $events);
}

test('hasNext returns true when not started', function() {
    $provider = makeInferenceProvider([]);
    $factory = makeCleanFactory();

    $generator = new CleanStreamingUpdateGenerator($provider, $factory);

    $execution = (new StructuredOutputExecution())->with(
        request: (new StructuredOutputRequest())->withMessages([['role' => 'user', 'content' => 'Test']]),
        responseModel: makeGeneratorTestResponseModel(),
        config: (new StructuredOutputConfig())->with(outputMode: OutputMode::JsonSchema),
    );

    expect($generator->hasNext($execution))->toBeTrue();
})->skip('PendingInference mocking issue - needs Feature test approach');

test('hasNext returns true when stream has more chunks', function() {
    $chunks = [
        new PartialInferenceResponse(contentDelta: '{"a": 1}', usage: Usage::none()),
        new PartialInferenceResponse(contentDelta: '{"b": 2}', usage: Usage::none()),
    ];

    $provider = makeInferenceProvider($chunks);
    $factory = makeCleanFactory();

    $generator = new CleanStreamingUpdateGenerator($provider, $factory);

    $execution = (new StructuredOutputExecution())->with(
        request: (new StructuredOutputRequest())->withMessages([['role' => 'user', 'content' => 'Test']]),
        responseModel: makeGeneratorTestResponseModel(),
        config: (new StructuredOutputConfig())->with(outputMode: OutputMode::JsonSchema),
    );

    // Initialize
    $execution = $generator->nextChunk($execution);

    expect($generator->hasNext($execution))->toBeTrue();
})->skip('PendingInference mocking issue - needs Feature test approach');

test('hasNext returns false when stream exhausted', function() {
    $chunks = [
        new PartialInferenceResponse(contentDelta: '{"a": 1}', usage: Usage::none()),
    ];

    $provider = makeInferenceProvider($chunks);
    $factory = makeCleanFactory();

    $generator = new CleanStreamingUpdateGenerator($provider, $factory);

    $execution = (new StructuredOutputExecution())->with(
        request: (new StructuredOutputRequest())->withMessages([['role' => 'user', 'content' => 'Test']]),
        responseModel: makeGeneratorTestResponseModel(),
        config: (new StructuredOutputConfig())->with(outputMode: OutputMode::JsonSchema),
    );

    // Process all chunks
    $execution = $generator->nextChunk($execution);
    $execution = $generator->nextChunk($execution);

    expect($generator->hasNext($execution))->toBeFalse();
})->skip('PendingInference mocking issue - needs Feature test approach');

test('nextChunk initializes stream on first call', function() {
    $chunks = [
        new PartialInferenceResponse(contentDelta: '{"test": true}', usage: Usage::none()),
    ];

    $provider = makeInferenceProvider($chunks);
    $factory = makeCleanFactory();

    $generator = new CleanStreamingUpdateGenerator($provider, $factory);

    $execution = (new StructuredOutputExecution())->with(
        request: (new StructuredOutputRequest())->withMessages([['role' => 'user', 'content' => 'Test']]),
        responseModel: makeGeneratorTestResponseModel(),
        config: (new StructuredOutputConfig())->with(outputMode: OutputMode::JsonSchema),
    );

    $result = $generator->nextChunk($execution);

    expect($result->attemptState())->not()->toBeNull()
        ->and($result->attemptState()->phase())->toBe(AttemptPhase::Streaming)
        ->and($result->attemptState()->isStreamInitialized())->toBeTrue();
})->skip('PendingInference mocking issue - needs Feature test approach');

test('nextChunk processes chunks sequentially', function() {
    $chunks = [
        new PartialInferenceResponse(contentDelta: '{"a": 1}', usage: Usage::none()),
        new PartialInferenceResponse(contentDelta: '{"b": 2}', usage: Usage::none()),
        new PartialInferenceResponse(contentDelta: '{"c": 3}', usage: Usage::none()),
    ];

    $provider = makeInferenceProvider($chunks);
    $factory = makeCleanFactory();

    $generator = new CleanStreamingUpdateGenerator($provider, $factory);

    $execution = (new StructuredOutputExecution())->with(
        request: (new StructuredOutputRequest())->withMessages([['role' => 'user', 'content' => 'Test']]),
        responseModel: makeGeneratorTestResponseModel(),
        config: (new StructuredOutputConfig())->with(outputMode: OutputMode::JsonSchema),
    );

    // Initialize and process first chunk
    $execution = $generator->nextChunk($execution);
    expect($generator->hasNext($execution))->toBeTrue();

    // Process second chunk
    $execution = $generator->nextChunk($execution);
    expect($generator->hasNext($execution))->toBeTrue();

    // Process third chunk
    $execution = $generator->nextChunk($execution);
    expect($generator->hasNext($execution))->toBeTrue();

    // Stream should now be exhausted
    $execution = $generator->nextChunk($execution);
    expect($generator->hasNext($execution))->toBeFalse();
})->skip('PendingInference mocking issue - needs Feature test approach');

test('nextChunk updates execution with current attempt', function() {
    $chunks = [
        new PartialInferenceResponse(contentDelta: '{"test": true}', usage: Usage::none()),
    ];

    $provider = makeInferenceProvider($chunks);
    $factory = makeCleanFactory();

    $generator = new CleanStreamingUpdateGenerator($provider, $factory);

    $execution = (new StructuredOutputExecution())->with(
        request: (new StructuredOutputRequest())->withMessages([['role' => 'user', 'content' => 'Test']]),
        responseModel: makeGeneratorTestResponseModel(),
        config: (new StructuredOutputConfig())->with(outputMode: OutputMode::JsonSchema),
    );

    // Initialize and process chunk
    $execution = $generator->nextChunk($execution);
    $execution = $generator->nextChunk($execution);

    expect($execution->currentInferenceResponse())->not()->toBeNull();
})->skip('PendingInference mocking issue - needs Feature test approach');

test('nextChunk accumulates partials in execution', function() {
    $chunks = [
        new PartialInferenceResponse(contentDelta: '{"a": 1}', usage: Usage::none()),
        new PartialInferenceResponse(contentDelta: '{"b": 2}', usage: Usage::none()),
    ];

    $provider = makeInferenceProvider($chunks);
    $factory = makeCleanFactory();

    $generator = new CleanStreamingUpdateGenerator($provider, $factory);

    $execution = (new StructuredOutputExecution())->with(
        request: (new StructuredOutputRequest())->withMessages([['role' => 'user', 'content' => 'Test']]),
        responseModel: makeGeneratorTestResponseModel(),
        config: (new StructuredOutputConfig())->with(outputMode: OutputMode::JsonSchema),
    );

    // Initialize
    $execution = $generator->nextChunk($execution);

    // Process first chunk
    $execution = $generator->nextChunk($execution);
    $partials1 = $execution->currentPartialInferenceResponses();

    // Process second chunk
    $execution = $generator->nextChunk($execution);
    $partials2 = $execution->currentPartialInferenceResponses();

    // Should have more partials after second chunk
    expect($partials2)->not()->toBeNull();
})->skip('PendingInference mocking issue - needs Feature test approach');

test('nextChunk marks stream as exhausted when no more chunks', function() {
    $chunks = [
        new PartialInferenceResponse(contentDelta: '{"test": true}', usage: Usage::none()),
    ];

    $provider = makeInferenceProvider($chunks);
    $factory = makeCleanFactory();

    $generator = new CleanStreamingUpdateGenerator($provider, $factory);

    $execution = (new StructuredOutputExecution())->with(
        request: (new StructuredOutputRequest())->withMessages([['role' => 'user', 'content' => 'Test']]),
        responseModel: makeGeneratorTestResponseModel(),
        config: (new StructuredOutputConfig())->with(outputMode: OutputMode::JsonSchema),
    );

    // Initialize and process all chunks
    $execution = $generator->nextChunk($execution);
    $execution = $generator->nextChunk($execution);

    expect($execution->attemptState()->hasMoreChunks())->toBeFalse();
})->skip('PendingInference mocking issue - needs Feature test approach');

test('handles empty chunk stream', function() {
    $provider = makeInferenceProvider([]);
    $factory = makeCleanFactory();

    $generator = new CleanStreamingUpdateGenerator($provider, $factory);

    $execution = (new StructuredOutputExecution())->with(
        request: (new StructuredOutputRequest())->withMessages([['role' => 'user', 'content' => 'Test']]),
        responseModel: makeGeneratorTestResponseModel(),
        config: (new StructuredOutputConfig())->with(outputMode: OutputMode::JsonSchema),
    );

    // Initialize
    $execution = $generator->nextChunk($execution);

    expect($execution->attemptState())->not()->toBeNull()
        ->and($execution->attemptState()->phase())->toBe(AttemptPhase::Streaming);
})->skip('PendingInference mocking issue - needs Feature test approach');

test('preserves existing errors in execution', function() {
    $chunks = [
        new PartialInferenceResponse(contentDelta: '{"test": true}', usage: Usage::none()),
    ];

    $provider = makeInferenceProvider($chunks);
    $factory = makeCleanFactory();

    $generator = new CleanStreamingUpdateGenerator($provider, $factory);

    $execution = StructuredOutputExecution::create(
        responseModel: makeGeneratorTestResponseModel(),
        mode: OutputMode::JsonSchema,
    )->withCurrentAttempt(
        inferenceResponse: null,
        partialInferenceResponses: null,
        errors: ['existing error'],
    );

    // Process chunk
    $execution = $generator->nextChunk($execution);
    $execution = $generator->nextChunk($execution);

    expect($execution->currentErrors())->toContain('existing error');
})->skip('PendingInference mocking issue - needs Feature test approach');

test('works with Tools output mode', function() {
    $chunks = [
        new PartialInferenceResponse(
            toolName: 'test_tool',
            toolArgs: '{"param": "value"}',
            usage: Usage::none()
        ),
    ];

    $provider = makeInferenceProvider($chunks);
    $factory = makeCleanFactory();

    $generator = new CleanStreamingUpdateGenerator($provider, $factory);

    $execution = (new StructuredOutputExecution())->with(
        request: (new StructuredOutputRequest())->withMessages([['role' => 'user', 'content' => 'Test']]),
        responseModel: makeGeneratorTestResponseModel(),
        config: (new StructuredOutputConfig())->with(outputMode: OutputMode::Tools),
    );

    // Initialize and process
    $execution = $generator->nextChunk($execution);
    $execution = $generator->nextChunk($execution);

    expect($execution->attemptState())->not()->toBeNull();
})->skip('PendingInference mocking issue - needs Feature test approach');
