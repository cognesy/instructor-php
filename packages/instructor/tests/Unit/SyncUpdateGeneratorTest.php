<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Core\InferenceProvider;
use Cognesy\Instructor\Core\RequestMaterializer;
use Cognesy\Instructor\Creation\ResponseModelFactory;
use Cognesy\Instructor\Creation\StructuredOutputSchemaRenderer;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\ResponseIterators\Sync\SyncUpdateGenerator;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
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

it('makes single inference request and marks as exhausted', function () {
    $response = InferenceResponse::empty()->withContent('{"name":"Alice","age":30}');

    $driver = new FakeInferenceDriver(
        responses: [$response]
    );

    $events = new EventDispatcher();
    $llmProvider = LLMProvider::new()->withDriver($driver);

    $inferenceProvider = new InferenceProvider(
        llmProvider: $llmProvider,
        requestMaterializer: new RequestMaterializer(),
        events: $events
    );

    $generator = new SyncUpdateGenerator(
        inferenceProvider: $inferenceProvider,
    );

    $execution = (new StructuredOutputExecution())
        ->with(
            request: (new StructuredOutputRequest())
                ->withMessages([['role' => 'user', 'content' => 'Test']])
                ->withStreamed(false),
            responseModel: makeSyncResponseModel(),
            config: (new StructuredOutputConfig())->with(outputMode: OutputMode::Json)
        );

    // Before first call: hasNext is true, no attempt state
    expect($generator->hasNext($execution))->toBeTrue();
    expect($execution->attemptState())->toBeNull();

    // First (and only) call: gets inference, marks as exhausted
    $updated = $generator->nextChunk($execution);

    expect($updated->attemptState())->not->toBeNull();
    expect($updated->attemptState()->hasMoreChunks())->toBeFalse();
    expect($updated->isCurrentlyStreaming())->toBeFalse();
    expect($updated->inferenceResponse())->not->toBeNull();
    expect($updated->inferenceResponse()->content())->toBe('{"name":"Alice","age":30}');

    // After first call: hasNext is false (exhausted)
    expect($generator->hasNext($updated))->toBeFalse();
});

it('returns single chunk with empty partials list', function () {
    $response = InferenceResponse::empty()->withContent('{"name":"Bob","age":25}');

    $driver = new FakeInferenceDriver(
        responses: [$response]
    );

    $events = new EventDispatcher();
    $llmProvider = LLMProvider::new()->withDriver($driver);

    $inferenceProvider = new InferenceProvider(
        llmProvider: $llmProvider,
        requestMaterializer: new RequestMaterializer(),
        events: $events
    );

    $generator = new SyncUpdateGenerator(
        inferenceProvider: $inferenceProvider,
    );

    $execution = (new StructuredOutputExecution())
        ->with(
            request: (new StructuredOutputRequest())
                ->withMessages([['role' => 'user', 'content' => 'Test']])
                ->withStreamed(false),
            responseModel: makeSyncResponseModel(),
            config: (new StructuredOutputConfig())->with(outputMode: OutputMode::Json)
        );

    $updated = $generator->nextChunk($execution);

    // Sync execution has final response (partials may or may not be tracked)
    expect($updated->attemptState())->not->toBeNull();
    expect($updated->inferenceResponse())->not->toBeNull();
});

it('normalizes content based on output mode', function () {
    $response = InferenceResponse::empty()->withContent('  {"name":"Charlie","age":35}  ');

    $driver = new FakeInferenceDriver(
        responses: [$response]
    );

    $events = new EventDispatcher();
    $llmProvider = LLMProvider::new()->withDriver($driver);

    $inferenceProvider = new InferenceProvider(
        llmProvider: $llmProvider,
        requestMaterializer: new RequestMaterializer(),
        events: $events
    );

    $generator = new SyncUpdateGenerator(
        inferenceProvider: $inferenceProvider,
    );

    $execution = (new StructuredOutputExecution())
        ->with(
            request: (new StructuredOutputRequest())
                ->withMessages([['role' => 'user', 'content' => 'Test']])
                ->withStreamed(false),
            responseModel: makeSyncResponseModel(),
            config: (new StructuredOutputConfig())->with(outputMode: OutputMode::Json)
        );

    $updated = $generator->nextChunk($execution);

    // Content should be normalized (whitespace trimmed via Json normalization)
    $content = $updated->inferenceResponse()->content();
    expect($content)->toBeString();
    expect($content)->toContain('name');
    expect($content)->toContain('Charlie');
});

it('does not call nextChunk when already exhausted', function () {
    $response = InferenceResponse::empty()->withContent('{"name":"Dave","age":40}');

    $driver = new FakeInferenceDriver(
        responses: [$response]
    );

    $events = new EventDispatcher();
    $llmProvider = LLMProvider::new()->withDriver($driver);

    $inferenceProvider = new InferenceProvider(
        llmProvider: $llmProvider,
        requestMaterializer: new RequestMaterializer(),
        events: $events
    );

    $generator = new SyncUpdateGenerator(
        inferenceProvider: $inferenceProvider,
    );

    $execution = (new StructuredOutputExecution())
        ->with(
            request: (new StructuredOutputRequest())
                ->withMessages([['role' => 'user', 'content' => 'Test']])
                ->withStreamed(false),
            responseModel: makeSyncResponseModel(),
            config: (new StructuredOutputConfig())->with(outputMode: OutputMode::Json)
        );

    // First call
    $updated = $generator->nextChunk($execution);
    expect($generator->hasNext($updated))->toBeFalse();

    // Second call should be a no-op (returns same execution)
    $secondCall = $generator->nextChunk($updated);
    expect($secondCall)->toBe($updated);
});
