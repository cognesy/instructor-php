<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Core\InferenceProvider;
use Cognesy\Instructor\Core\RequestMaterializer;
use Cognesy\Instructor\Core\ResponseGenerator;
use Cognesy\Instructor\Core\SyncExecutionDriver;
use Cognesy\Instructor\Creation\ResponseModelFactory;
use Cognesy\Instructor\Creation\StructuredOutputSchemaRenderer;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Extraction\ResponseExtractor;
use Cognesy\Instructor\RetryPolicy\DefaultRetryPolicy;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Instructor\Validation\ResponseValidator;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;

class SyncModel {
    public string $name;
    public int $age;
}

function makeSyncResponseModel(): ResponseModel {
    $cfg = new StructuredOutputConfig();
    $factory = new ResponseModelFactory(
        new StructuredOutputSchemaRenderer($cfg),
        $cfg,
        new EventDispatcher()
    );
    return $factory->fromAny(SyncModel::class);
}

function makeSyncExecution(): StructuredOutputExecution {
    return (new StructuredOutputExecution())
        ->with(
            request: (new StructuredOutputRequest())
                ->withMessages([['role' => 'user', 'content' => 'Test']])
                ->withStreamed(false),
            responseModel: makeSyncResponseModel(),
            config: (new StructuredOutputConfig())->with(outputMode: OutputMode::Json)
        );
}

function makeSyncDriver(
    EventDispatcher $events,
    InferenceProvider $inferenceProvider,
    StructuredOutputExecution $execution,
): SyncExecutionDriver {
    return new SyncExecutionDriver(
        execution: $execution,
        inferenceProvider: $inferenceProvider,
        responseGenerator: new ResponseGenerator(
            responseDeserializer: new ResponseDeserializer(
                events: $events,
                deserializers: [SymfonyDeserializer::class],
                config: new StructuredOutputConfig(),
            ),
            responseValidator: new ResponseValidator($events, [], new StructuredOutputConfig()),
            responseTransformer: new ResponseTransformer($events, [], new StructuredOutputConfig()),
            events: $events,
            extractor: new ResponseExtractor(events: $events),
        ),
        retryPolicy: new DefaultRetryPolicy($events),
    );
}

function makeInferenceProvider(FakeInferenceDriver $fakeDriver): InferenceProvider {
    return new InferenceProvider(
        inference: InferenceRuntime::fromProvider(LLMProvider::new()->withDriver($fakeDriver)),
        requestMaterializer: new RequestMaterializer(),
    );
}

it('makes single inference request and marks as exhausted', function () {
    $response = InferenceResponse::empty()->withContent('{"name":"Alice","age":30}');
    $fakeDriver = new FakeInferenceDriver(responses: [$response]);
    $events = new EventDispatcher();
    $execution = makeSyncExecution();

    $driver = makeSyncDriver($events, makeInferenceProvider($fakeDriver), $execution);

    expect($driver->hasNextEmission())->toBeTrue();

    $emission = $driver->nextEmission();
    expect($emission)->not->toBeNull();
    expect($emission->isFinal())->toBeTrue();

    $updated = $driver->execution();
    expect($updated->isFinalized())->toBeTrue();
    expect($updated->inferenceResponse())->not->toBeNull();
    expect($updated->inferenceResponse()->content())->toBe('{"name":"Alice","age":30}');

    expect($driver->hasNextEmission())->toBeFalse();
});

it('finalizes sync execution with an empty partial response snapshot', function () {
    $response = InferenceResponse::empty()->withContent('{"name":"Bob","age":25}');
    $fakeDriver = new FakeInferenceDriver(responses: [$response]);
    $events = new EventDispatcher();
    $execution = makeSyncExecution();

    $driver = makeSyncDriver($events, makeInferenceProvider($fakeDriver), $execution);

    $emission = $driver->nextEmission();
    expect($emission)->not->toBeNull();

    $updated = $driver->execution();
    expect($updated->inferenceResponse())->not->toBeNull();
    expect($updated->activeAttempt())->toBeNull();
    expect($updated->lastFinalizedAttempt())->not->toBeNull();
    expect($updated->lastFinalizedAttempt()?->isFinalized())->toBeTrue();
    expect($updated->lastFinalizedAttempt()?->inferenceResponse()?->content())->toBe('{"name":"Bob","age":25}');
});

it('normalizes content based on output mode', function () {
    $response = InferenceResponse::empty()->withContent('  {"name":"Charlie","age":35}  ');
    $fakeDriver = new FakeInferenceDriver(responses: [$response]);
    $events = new EventDispatcher();
    $execution = makeSyncExecution();

    $driver = makeSyncDriver($events, makeInferenceProvider($fakeDriver), $execution);

    $emission = $driver->nextEmission();
    expect($emission)->not->toBeNull();

    $updated = $driver->execution();
    $content = $updated->inferenceResponse()->content();
    expect($content)->toBeString();
    expect($content)->toContain('name');
    expect($content)->toContain('Charlie');
});

it('returns no emission when already completed', function () {
    $response = InferenceResponse::empty()->withContent('{"name":"Dave","age":40}');
    $fakeDriver = new FakeInferenceDriver(responses: [$response]);
    $events = new EventDispatcher();
    $execution = makeSyncExecution();

    $driver = makeSyncDriver($events, makeInferenceProvider($fakeDriver), $execution);

    // First emission
    $emission = $driver->nextEmission();
    expect($emission)->not->toBeNull();
    expect($driver->hasNextEmission())->toBeFalse();

    // Second call returns null
    $second = $driver->nextEmission();
    expect($second)->toBeNull();
});
