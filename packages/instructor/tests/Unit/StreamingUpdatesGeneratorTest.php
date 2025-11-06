<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\PartialsGeneratorConfig;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Core\InferenceProvider;
use Cognesy\Instructor\Core\RequestMaterializer;
use Cognesy\Instructor\Creation\ResponseModelFactory;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Executors\Streaming\PartialGen\GeneratePartialsFromJson;
use Cognesy\Instructor\Executors\Streaming\StreamingUpdatesGenerator;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Instructor\Validation\PartialValidation;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Schema\Factories\SchemaFactory;
use Cognesy\Schema\Factories\ToolCallBuilder;

class LegacyStreamModel {
    public string $name;
    public int $age;
}

function makeLegacyStreamResponseModel(): ResponseModel {
    $cfg = new StructuredOutputConfig();
    $schemaFactory = new SchemaFactory(useObjectReferences: $cfg->useObjectReferences());
    $factory = new ResponseModelFactory(new ToolCallBuilder($schemaFactory), $schemaFactory, $cfg, new EventDispatcher());
    return $factory->fromAny(LegacyStreamModel::class);
}

it('initializes stream on first call', function () {
    $driver = new FakeInferenceDriver(
        streamBatches: [
            [
                new PartialInferenceResponse(contentDelta: '{"name":"'),
                new PartialInferenceResponse(contentDelta: 'Alice","age":30}'),
            ],
        ]
    );

    $events = new EventDispatcher();
    $llmProvider = LLMProvider::new()->withDriver($driver);

    $inferenceProvider = new InferenceProvider(
        llmProvider: $llmProvider,
        requestMaterializer: new RequestMaterializer(),
        events: $events
    );

    $partialsGenerator = new GeneratePartialsFromJson(
        responseDeserializer: new ResponseDeserializer($events, [\Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer::class], new StructuredOutputConfig()),
        partialValidator: new PartialValidation(new PartialsGeneratorConfig()),
        responseTransformer: new ResponseTransformer($events, [], new StructuredOutputConfig()),
        events: $events
    );

    $generator = new StreamingUpdatesGenerator(
        inferenceProvider: $inferenceProvider,
        partialsGenerator: $partialsGenerator
    );

    $execution = (new StructuredOutputExecution())
        ->with(
            request: (new StructuredOutputRequest())
                ->withMessages([['role' => 'user', 'content' => 'Test']])
                ->withStreamed(true),
            responseModel: makeLegacyStreamResponseModel(),
            config: (new StructuredOutputConfig())->with(outputMode: OutputMode::Json)
        );

    expect($generator->hasNext($execution))->toBeTrue();
    expect($execution->attemptState())->toBeNull();

    // First call initializes stream
    $updated = $generator->nextChunk($execution);

    expect($updated->attemptState())->not->toBeNull();
    expect($updated->attemptState()->isStreamInitialized())->toBeTrue();
});

it('processes streaming chunks sequentially', function () {
    $driver = new FakeInferenceDriver(
        streamBatches: [
            [
                new PartialInferenceResponse(contentDelta: '{"name":"'),
                new PartialInferenceResponse(contentDelta: 'Bob","age":'),
                new PartialInferenceResponse(contentDelta: '25}'),
            ],
        ]
    );

    $events = new EventDispatcher();
    $llmProvider = LLMProvider::new()->withDriver($driver);

    $inferenceProvider = new InferenceProvider(
        llmProvider: $llmProvider,
        requestMaterializer: new RequestMaterializer(),
        events: $events
    );

    $partialsGenerator = new GeneratePartialsFromJson(
        responseDeserializer: new ResponseDeserializer($events, [\Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer::class], new StructuredOutputConfig()),
        partialValidator: new PartialValidation(new PartialsGeneratorConfig()),
        responseTransformer: new ResponseTransformer($events, [], new StructuredOutputConfig()),
        events: $events
    );

    $generator = new StreamingUpdatesGenerator(
        inferenceProvider: $inferenceProvider,
        partialsGenerator: $partialsGenerator
    );

    $execution = (new StructuredOutputExecution())
        ->with(
            request: (new StructuredOutputRequest())
                ->withMessages([['role' => 'user', 'content' => 'Test']])
                ->withStreamed(true),
            responseModel: makeLegacyStreamResponseModel(),
            config: (new StructuredOutputConfig())->with(outputMode: OutputMode::Json)
        );

    $updates = [];
    while ($generator->hasNext($execution)) {
        $execution = $generator->nextChunk($execution);
        $updates[] = $execution;
    }

    // Should have multiple updates (one per chunk + initialization)
    expect($updates)->not->toBeEmpty();

    // Last update should have exhausted stream
    $lastUpdate = end($updates);
    expect($lastUpdate->attemptState())->not->toBeNull();
    expect($lastUpdate->attemptState()->hasMoreChunks())->toBeFalse();
});

it('preserves attempt identity across chunks', function () {
    $driver = new FakeInferenceDriver(
        streamBatches: [
            [
                new PartialInferenceResponse(contentDelta: '{"name":"Charlie",'),
                new PartialInferenceResponse(contentDelta: '"age":35}'),
            ],
        ]
    );

    $events = new EventDispatcher();
    $llmProvider = LLMProvider::new()->withDriver($driver);

    $inferenceProvider = new InferenceProvider(
        llmProvider: $llmProvider,
        requestMaterializer: new RequestMaterializer(),
        events: $events
    );

    $partialsGenerator = new GeneratePartialsFromJson(
        responseDeserializer: new ResponseDeserializer($events, [\Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer::class], new StructuredOutputConfig()),
        partialValidator: new PartialValidation(new PartialsGeneratorConfig()),
        responseTransformer: new ResponseTransformer($events, [], new StructuredOutputConfig()),
        events: $events
    );

    $generator = new StreamingUpdatesGenerator(
        inferenceProvider: $inferenceProvider,
        partialsGenerator: $partialsGenerator
    );

    $execution = (new StructuredOutputExecution())
        ->with(
            request: (new StructuredOutputRequest())
                ->withMessages([['role' => 'user', 'content' => 'Test']])
                ->withStreamed(true),
            responseModel: makeLegacyStreamResponseModel(),
            config: (new StructuredOutputConfig())->with(outputMode: OutputMode::Json)
        );

    $attemptIds = [];
    $executionIds = [];

    while ($generator->hasNext($execution)) {
        $execution = $generator->nextChunk($execution);
        if ($execution->currentAttempt()) {
            $attemptIds[] = $execution->currentAttempt()->id;
            $executionIds[] = $execution->currentAttempt()->inferenceExecution()->id;
        }
    }

    // All chunks should update the SAME attempt (same ID)
    expect(array_unique($attemptIds))->toHaveCount(1);

    // All chunks should update the SAME inference execution (same ID)
    expect(array_unique($executionIds))->toHaveCount(1);
});

it('returns false for hasNext when stream is exhausted', function () {
    $driver = new FakeInferenceDriver(
        streamBatches: [
            [
                new PartialInferenceResponse(contentDelta: '{"name":"Dave","age":40}'),
            ],
        ]
    );

    $events = new EventDispatcher();
    $llmProvider = LLMProvider::new()->withDriver($driver);

    $inferenceProvider = new InferenceProvider(
        llmProvider: $llmProvider,
        requestMaterializer: new RequestMaterializer(),
        events: $events
    );

    $partialsGenerator = new GeneratePartialsFromJson(
        responseDeserializer: new ResponseDeserializer($events, [\Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer::class], new StructuredOutputConfig()),
        partialValidator: new PartialValidation(new PartialsGeneratorConfig()),
        responseTransformer: new ResponseTransformer($events, [], new StructuredOutputConfig()),
        events: $events
    );

    $generator = new StreamingUpdatesGenerator(
        inferenceProvider: $inferenceProvider,
        partialsGenerator: $partialsGenerator
    );

    $execution = (new StructuredOutputExecution())
        ->with(
            request: (new StructuredOutputRequest())
                ->withMessages([['role' => 'user', 'content' => 'Test']])
                ->withStreamed(true),
            responseModel: makeLegacyStreamResponseModel(),
            config: (new StructuredOutputConfig())->with(outputMode: OutputMode::Json)
        );

    // Process all chunks
    while ($generator->hasNext($execution)) {
        $execution = $generator->nextChunk($execution);
    }

    // After exhaustion, hasNext should be false
    expect($generator->hasNext($execution))->toBeFalse();
});
