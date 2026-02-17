<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Core\InferenceProvider;
use Cognesy\Instructor\Core\RequestMaterializer;
use Cognesy\Instructor\Creation\ResponseModelFactory;
use Cognesy\Instructor\Creation\StructuredOutputSchemaRenderer;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Enums\AttemptPhase;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\ModularStreamFactory;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\ModularUpdateGenerator;
use Cognesy\Instructor\Tests\Support\FakeInferenceRequestDriver;
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;

class TestGeneratorModel {
    public string $test = '';
}

function makeModularUpdateGeneratorTestInfrastructure(FakeInferenceRequestDriver $driver): array {
    $events = new EventDispatcher();
    $config = new StructuredOutputConfig();

    $schemaRenderer = new StructuredOutputSchemaRenderer($config);
    $responseModelFactory = new ResponseModelFactory(
        $schemaRenderer,
        $config,
        $events
    );

    $llmProvider = LLMProvider::using('openai')
        ->withDriver($driver);

    $inferenceProvider = new InferenceProvider(
        InferenceRuntime::fromProvider($llmProvider),
        new RequestMaterializer(),
    );

    $deserializer = new ResponseDeserializer($events, [SymfonyDeserializer::class], $config);
    $transformer = new ResponseTransformer(events: $events, transformers: [], config: $config);

    $factory = new ModularStreamFactory($deserializer, $transformer, $events);

    $generator = new ModularUpdateGenerator($inferenceProvider, $factory);

    $responseModel = $responseModelFactory->fromAny(TestGeneratorModel::class);

    return [$generator, $responseModel, $config];
}

function makeModularUpdateGeneratorExecution($responseModel, $config, $mode = OutputMode::JsonSchema): StructuredOutputExecution {
    return (new StructuredOutputExecution())->with(
        request: (new StructuredOutputRequest(
            messages: [['role' => 'user', 'content' => 'Test']],
            options: ['stream' => true]
        )),
        responseModel: $responseModel,
        config: $config->with(outputMode: $mode),
    );
}

test('hasNext returns true when not started', function() {
    $driver = new FakeInferenceRequestDriver(streamBatches: [[]]);
    [$generator, $responseModel, $config] = makeModularUpdateGeneratorTestInfrastructure($driver);
    $execution = makeModularUpdateGeneratorExecution($responseModel, $config);

    expect($generator->hasNext($execution))->toBeTrue();
});

test('hasNext returns true when stream has more chunks', function() {
    $chunks = [
        new PartialInferenceResponse(contentDelta: '{"test": "va'),
        new PartialInferenceResponse(contentDelta: 'lue"}'),
    ];

    $driver = new FakeInferenceRequestDriver(streamBatches: [$chunks]);
    [$generator, $responseModel, $config] = makeModularUpdateGeneratorTestInfrastructure($driver);
    $execution = makeModularUpdateGeneratorExecution($responseModel, $config);

    // Initialize
    $execution = $generator->nextChunk($execution);

    expect($generator->hasNext($execution))->toBeTrue();
});

test('hasNext returns false when stream exhausted', function() {
    $chunks = [
        new PartialInferenceResponse(contentDelta: '{"test": "value"}'),
    ];

    $driver = new FakeInferenceRequestDriver(streamBatches: [$chunks]);
    [$generator, $responseModel, $config] = makeModularUpdateGeneratorTestInfrastructure($driver);
    $execution = makeModularUpdateGeneratorExecution($responseModel, $config);

    // Process all chunks
    $execution = $generator->nextChunk($execution);
    $execution = $generator->nextChunk($execution);

    expect($generator->hasNext($execution))->toBeFalse();
});

test('nextChunk initializes stream on first call', function() {
    $chunks = [
        new PartialInferenceResponse(contentDelta: '{"test": "value"}'),
    ];

    $driver = new FakeInferenceRequestDriver(streamBatches: [$chunks]);
    [$generator, $responseModel, $config] = makeModularUpdateGeneratorTestInfrastructure($driver);
    $execution = makeModularUpdateGeneratorExecution($responseModel, $config);

    $result = $generator->nextChunk($execution);

    expect($result->attemptState())->not()->toBeNull()
        ->and($result->attemptState()->attemptPhase())->toBe(AttemptPhase::Streaming)
        ->and($result->attemptState()->isStreamInitialized())->toBeTrue();
});

test('nextChunk processes chunks sequentially', function() {
    $chunks = [
        new PartialInferenceResponse(contentDelta: '{"test": "v'),
        new PartialInferenceResponse(contentDelta: 'al'),
        new PartialInferenceResponse(contentDelta: 'ue"}'),
    ];

    $driver = new FakeInferenceRequestDriver(streamBatches: [$chunks]);
    [$generator, $responseModel, $config] = makeModularUpdateGeneratorTestInfrastructure($driver);
    $execution = makeModularUpdateGeneratorExecution($responseModel, $config);

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
});

test('nextChunk updates execution with current attempt', function() {
    $chunks = [
        new PartialInferenceResponse(contentDelta: '{"test": "value"}'),
    ];

    $driver = new FakeInferenceRequestDriver(streamBatches: [$chunks]);
    [$generator, $responseModel, $config] = makeModularUpdateGeneratorTestInfrastructure($driver);
    $execution = makeModularUpdateGeneratorExecution($responseModel, $config);

    // Initialize and process chunk
    $execution = $generator->nextChunk($execution);
    $execution = $generator->nextChunk($execution);

    expect($execution->currentAttempt()->inferenceResponse())->not()->toBeNull();
});

test('nextChunk accumulates partials in execution', function() {
    $chunks = [
        new PartialInferenceResponse(contentDelta: '{"test": "v'),
        new PartialInferenceResponse(contentDelta: 'alue"}'),
    ];

    $driver = new FakeInferenceRequestDriver(streamBatches: [$chunks]);
    [$generator, $responseModel, $config] = makeModularUpdateGeneratorTestInfrastructure($driver);
    $execution = makeModularUpdateGeneratorExecution($responseModel, $config);

    // Initialize
    $execution = $generator->nextChunk($execution);

    // Process first chunk
    $execution = $generator->nextChunk($execution);
    $partial1 = $execution->currentAttempt()->inferenceExecution()->partialResponse();

    // Process second chunk
    $execution = $generator->nextChunk($execution);
    $partial2 = $execution->currentAttempt()->inferenceExecution()->partialResponse();

    // Should have accumulated content from both chunks
    expect($partial2)->not()->toBeNull()
        ->and($partial2->content())->toBe('{"test": "value"}');
});

test('nextChunk marks stream as exhausted when no more chunks', function() {
    $chunks = [
        new PartialInferenceResponse(contentDelta: '{"test": "value"}'),
    ];

    $driver = new FakeInferenceRequestDriver(streamBatches: [$chunks]);
    [$generator, $responseModel, $config] = makeModularUpdateGeneratorTestInfrastructure($driver);
    $execution = makeModularUpdateGeneratorExecution($responseModel, $config);

    // Initialize and process all chunks
    $execution = $generator->nextChunk($execution);
    $execution = $generator->nextChunk($execution);

    expect($execution->attemptState()->hasMoreChunks())->toBeFalse();
});

test('handles empty chunk stream', function() {
    $driver = new FakeInferenceRequestDriver(streamBatches: [[]]);
    [$generator, $responseModel, $config] = makeModularUpdateGeneratorTestInfrastructure($driver);
    $execution = makeModularUpdateGeneratorExecution($responseModel, $config);

    // Initialize
    $execution = $generator->nextChunk($execution);

    expect($execution->attemptState())->not()->toBeNull()
        ->and($execution->attemptState()->attemptPhase())->toBe(AttemptPhase::Streaming);
});

test('preserves existing errors in execution', function() {
    $chunks = [
        new PartialInferenceResponse(contentDelta: '{"test": "value"}'),
    ];

    $driver = new FakeInferenceRequestDriver(streamBatches: [$chunks]);
    [$generator, $responseModel, $config] = makeModularUpdateGeneratorTestInfrastructure($driver);

    $execution = makeModularUpdateGeneratorExecution($responseModel, $config)
        ->withCurrentAttempt(
            inferenceResponse: new InferenceResponse(content: ''),
            partialInferenceResponse: PartialInferenceResponse::empty(),
            errors: ['existing error'],
        );

    // Process chunk
    $execution = $generator->nextChunk($execution);
    $execution = $generator->nextChunk($execution);

    expect($execution->currentErrors())->toContain('existing error');
});

test('works with Tools output mode', function() {
    $chunks = [
        new PartialInferenceResponse(
            toolName: 'test_tool',
            toolArgs: '{"param": "value"}',
            usage: Usage::none()
        ),
    ];

    $driver = new FakeInferenceRequestDriver(streamBatches: [$chunks]);
    [$generator, $responseModel, $config] = makeModularUpdateGeneratorTestInfrastructure($driver);
    $execution = makeModularUpdateGeneratorExecution($responseModel, $config, OutputMode::Tools);

    // Initialize and process
    $execution = $generator->nextChunk($execution);
    $execution = $generator->nextChunk($execution);

    expect($execution->attemptState())->not()->toBeNull();
});
