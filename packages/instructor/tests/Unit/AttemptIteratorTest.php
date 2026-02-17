<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Core\AttemptIterator;
use Cognesy\Instructor\Core\InferenceProvider;
use Cognesy\Instructor\Core\RequestMaterializer;
use Cognesy\Instructor\Core\ResponseGenerator;
use Cognesy\Instructor\Creation\ResponseModelFactory;
use Cognesy\Instructor\Creation\StructuredOutputSchemaRenderer;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Extraction\ResponseExtractor;
use Cognesy\Instructor\Exceptions\StructuredOutputRecoveryException;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\ModularStreamFactory;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\ModularUpdateGenerator;
use Cognesy\Instructor\RetryPolicy\DefaultRetryPolicy;
use Cognesy\Instructor\Tests\Support\FakeInferenceRequestDriver;
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Instructor\Validation\ResponseValidator;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;

class AttemptTestModel {
    public string $name;
    public int $age;
}

function makeAttemptTestResponseModel(): ResponseModel {
    $cfg = new StructuredOutputConfig();
    $factory = new ResponseModelFactory(
        new StructuredOutputSchemaRenderer($cfg),
        $cfg,
        new EventDispatcher()
    );
    return $factory->fromAny(AttemptTestModel::class);
}

function makeModularStreamIterator(EventDispatcher $events, InferenceProvider $inferenceProvider): ModularUpdateGenerator {
    $factory = new ModularStreamFactory(
        deserializer: new ResponseDeserializer($events, [\Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer::class], new StructuredOutputConfig()),
        transformer: new ResponseTransformer($events, [], new StructuredOutputConfig()),
        events: $events,
        bufferFactory: null,
    );

    return new ModularUpdateGenerator(
        inferenceProvider: $inferenceProvider,
        factory: $factory,
    );
}

it('processes successful streaming attempt end-to-end', function () {
    $driver = new FakeInferenceRequestDriver(
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
        inference: InferenceRuntime::fromProvider($llmProvider),
        requestMaterializer: new RequestMaterializer(),
        events: $events
    );

    $streamIterator = makeModularStreamIterator($events, $inferenceProvider);

    $responseGenerator = new ResponseGenerator(
        responseDeserializer: new ResponseDeserializer($events, [\Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer::class], new StructuredOutputConfig()),
        responseValidator: new ResponseValidator($events, [], new StructuredOutputConfig()),
        responseTransformer: new ResponseTransformer($events, [], new StructuredOutputConfig()),
        events: $events,
        extractor: new ResponseExtractor(events: $events),
    );

    $retryPolicy = new DefaultRetryPolicy($events);

    $iterator = new AttemptIterator(
        streamIterator: $streamIterator,
        responseGenerator: $responseGenerator,
        retryPolicy: $retryPolicy
    );

    $execution = (new StructuredOutputExecution())
        ->with(
            request: (new StructuredOutputRequest())
                ->withMessages([['role' => 'user', 'content' => 'Test']])
                ->withStreamed(true),
            responseModel: makeAttemptTestResponseModel(),
            config: (new StructuredOutputConfig())->with(outputMode: OutputMode::Json)
        );

    // Process all updates
    $updates = [];
    while ($iterator->hasNext($execution)) {
        $execution = $iterator->nextUpdate($execution);
        $updates[] = $execution;
    }

    // Should have multiple updates
    expect($updates)->not->toBeEmpty();

    // Final execution should be successful and finalized
    expect($execution->isFinalized())->toBeTrue();
    expect($execution->isSuccessful())->toBeTrue();
    expect($execution->attemptCount())->toBe(1);

    // Should have exactly one attempt in the attempts list
    expect($execution->attempts()->count())->toBe(1);
});

it('retries on validation failure when retries available', function () {
    // First batch fails validation, second succeeds
    $driver = new FakeInferenceRequestDriver(
        streamBatches: [
            // First attempt - invalid age (string instead of int)
            [
                new PartialInferenceResponse(contentDelta: '{"name":"Bob","age":"invalid"}'),
            ],
            // Second attempt - valid
            [
                new PartialInferenceResponse(contentDelta: '{"name":"Bob","age":25}'),
            ],
        ]
    );

    $events = new EventDispatcher();
    $llmProvider = LLMProvider::new()->withDriver($driver);

    $inferenceProvider = new InferenceProvider(
        inference: InferenceRuntime::fromProvider($llmProvider),
        requestMaterializer: new RequestMaterializer(),
        events: $events
    );

    $streamIterator = makeModularStreamIterator($events, $inferenceProvider);

    $responseGenerator = new ResponseGenerator(
        responseDeserializer: new ResponseDeserializer($events, [\Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer::class], new StructuredOutputConfig()),
        responseValidator: new ResponseValidator($events, [], new StructuredOutputConfig()),
        responseTransformer: new ResponseTransformer($events, [], new StructuredOutputConfig()),
        events: $events,
        extractor: new ResponseExtractor(events: $events),
    );

    $retryPolicy = new DefaultRetryPolicy($events);

    $iterator = new AttemptIterator(
        streamIterator: $streamIterator,
        responseGenerator: $responseGenerator,
        retryPolicy: $retryPolicy
    );

    $execution = (new StructuredOutputExecution())
        ->with(
            request: (new StructuredOutputRequest())
                ->withMessages([['role' => 'user', 'content' => 'Test']])
                ->withStreamed(true),
            responseModel: makeAttemptTestResponseModel(),
            config: (new StructuredOutputConfig())->with(
                outputMode: OutputMode::Json,
                maxRetries: 2
            )
        );

    // Process all updates
    while ($iterator->hasNext($execution)) {
        $execution = $iterator->nextUpdate($execution);
    }

    // Should be successful after retry
    expect($execution->isFinalized())->toBeTrue();
    expect($execution->isSuccessful())->toBeTrue();

    // Should have 2 attempts (1 failed + 1 successful)
    expect($execution->attempts()->count())->toBe(2);
});

it('throws exception when max retries exceeded', function () {
    // Both attempts fail validation
    $driver = new FakeInferenceRequestDriver(
        streamBatches: [
            [new PartialInferenceResponse(contentDelta: '{"name":"Charlie","age":"bad"}')],
            [new PartialInferenceResponse(contentDelta: '{"name":"Charlie","age":"also bad"}')],
        ]
    );

    $events = new EventDispatcher();
    $llmProvider = LLMProvider::new()->withDriver($driver);

    $inferenceProvider = new InferenceProvider(
        inference: InferenceRuntime::fromProvider($llmProvider),
        requestMaterializer: new RequestMaterializer(),
        events: $events
    );

    $streamIterator = makeModularStreamIterator($events, $inferenceProvider);

    $responseGenerator = new ResponseGenerator(
        responseDeserializer: new ResponseDeserializer($events, [\Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer::class], new StructuredOutputConfig()),
        responseValidator: new ResponseValidator($events, [], new StructuredOutputConfig()),
        responseTransformer: new ResponseTransformer($events, [], new StructuredOutputConfig()),
        events: $events,
        extractor: new ResponseExtractor(events: $events),
    );

    $retryPolicy = new DefaultRetryPolicy($events);

    $iterator = new AttemptIterator(
        streamIterator: $streamIterator,
        responseGenerator: $responseGenerator,
        retryPolicy: $retryPolicy
    );

    $execution = (new StructuredOutputExecution())
        ->with(
            request: (new StructuredOutputRequest())
                ->withMessages([['role' => 'user', 'content' => 'Test']])
                ->withStreamed(true),
            responseModel: makeAttemptTestResponseModel(),
            config: (new StructuredOutputConfig())->with(
                outputMode: OutputMode::Json,
                maxRetries: 1 // Only 1 retry allowed
            )
        );

    // Should throw after exhausting retries
    expect(function() use ($iterator, $execution) {
        while ($iterator->hasNext($execution)) {
            $execution = $iterator->nextUpdate($execution);
        }
    })->toThrow(StructuredOutputRecoveryException::class);
});

it('hasNext returns false when execution is finalized', function () {
    $driver = new FakeInferenceRequestDriver(
        streamBatches: [
            [
                new PartialInferenceResponse(contentDelta: '{"name":"Dave","age":40}'),
            ],
        ]
    );

    $events = new EventDispatcher();
    $llmProvider = LLMProvider::new()->withDriver($driver);

    $inferenceProvider = new InferenceProvider(
        inference: InferenceRuntime::fromProvider($llmProvider),
        requestMaterializer: new RequestMaterializer(),
        events: $events
    );

    $streamIterator = makeModularStreamIterator($events, $inferenceProvider);

    $responseGenerator = new ResponseGenerator(
        responseDeserializer: new ResponseDeserializer($events, [\Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer::class], new StructuredOutputConfig()),
        responseValidator: new ResponseValidator($events, [], new StructuredOutputConfig()),
        responseTransformer: new ResponseTransformer($events, [], new StructuredOutputConfig()),
        events: $events,
        extractor: new ResponseExtractor(events: $events),
    );

    $retryPolicy = new DefaultRetryPolicy($events);

    $iterator = new AttemptIterator(
        streamIterator: $streamIterator,
        responseGenerator: $responseGenerator,
        retryPolicy: $retryPolicy
    );

    $execution = (new StructuredOutputExecution())
        ->with(
            request: (new StructuredOutputRequest())
                ->withMessages([['role' => 'user', 'content' => 'Test']])
                ->withStreamed(true),
            responseModel: makeAttemptTestResponseModel(),
            config: (new StructuredOutputConfig())->with(outputMode: OutputMode::Json)
        );

    // Process all updates
    while ($iterator->hasNext($execution)) {
        $execution = $iterator->nextUpdate($execution);
    }

    // After finalization, hasNext should be false
    expect($iterator->hasNext($execution))->toBeFalse();
    expect($execution->isFinalized())->toBeTrue();
});

it('clears attempt state between attempts', function () {
    // First attempt fails, second succeeds
    $driver = new FakeInferenceRequestDriver(
        streamBatches: [
            [new PartialInferenceResponse(contentDelta: '{"name":"Eve","age":"wrong"}')],
            [new PartialInferenceResponse(contentDelta: '{"name":"Eve","age":28}')],
        ]
    );

    $events = new EventDispatcher();
    $llmProvider = LLMProvider::new()->withDriver($driver);

    $inferenceProvider = new InferenceProvider(
        inference: InferenceRuntime::fromProvider($llmProvider),
        requestMaterializer: new RequestMaterializer(),
        events: $events
    );

    $streamIterator = makeModularStreamIterator($events, $inferenceProvider);

    $responseGenerator = new ResponseGenerator(
        responseDeserializer: new ResponseDeserializer($events, [\Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer::class], new StructuredOutputConfig()),
        responseValidator: new ResponseValidator($events, [], new StructuredOutputConfig()),
        responseTransformer: new ResponseTransformer($events, [], new StructuredOutputConfig()),
        events: $events,
        extractor: new ResponseExtractor(events: $events),
    );

    $retryPolicy = new DefaultRetryPolicy($events);

    $iterator = new AttemptIterator(
        streamIterator: $streamIterator,
        responseGenerator: $responseGenerator,
        retryPolicy: $retryPolicy
    );

    $execution = (new StructuredOutputExecution())
        ->with(
            request: (new StructuredOutputRequest())
                ->withMessages([['role' => 'user', 'content' => 'Test']])
                ->withStreamed(true),
            responseModel: makeAttemptTestResponseModel(),
            config: (new StructuredOutputConfig())->with(
                outputMode: OutputMode::Json,
                maxRetries: 2
            )
        );

    $stateTransitions = [];
    while ($iterator->hasNext($execution)) {
        $execution = $iterator->nextUpdate($execution);
        $stateTransitions[] = $execution->isCurrentlyStreaming() ? 'streaming' : 'not-streaming';
    }

    // Should see pattern: attempt-active → not-active (after first attempt fails)
    // → attempt-active again (second attempt starts) → not-active (finalized)
    expect($stateTransitions)->toContain('streaming');
    expect($stateTransitions)->toContain('not-streaming');
});
