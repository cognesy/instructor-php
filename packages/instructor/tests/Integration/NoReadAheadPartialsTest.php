<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Core\InferenceProvider;
use Cognesy\Instructor\Core\RequestMaterializer;
use Cognesy\Instructor\Creation\ResponseModelFactory;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Executors\Partials\PartialStreamFactory;
use Cognesy\Instructor\Executors\Partials\PartialStreamingUpdateGenerator;
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Instructor\Validation\PartialValidation;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Schema\Factories\SchemaFactory;
use Cognesy\Schema\Factories\ToolCallBuilder;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;

require_once __DIR__ . '/Support/ProbeIterator.php';
require_once __DIR__ . '/Support/ProbeStreamDriver.php';

class NRAP_User { public string $name; public int $age; }

it('does not buffer whole stream on init in partials pipeline', function () {
    $parts = [
        new PartialInferenceResponse(contentDelta: '{"name":"A"'),
        new PartialInferenceResponse(contentDelta: 'nn","age":'),
        new PartialInferenceResponse(contentDelta: '30}', finishReason: 'stop'),
    ];

    $driver = new FakeInferenceDriver(streamBatches: [ $parts ]);
    $events = new EventDispatcher();
    $llm = LLMProvider::new()->withDriver($driver);

    $cfg = new StructuredOutputConfig();
    $schemaFactory = new SchemaFactory(useObjectReferences: $cfg->useObjectReferences());
    $responseFactory = new ResponseModelFactory(new ToolCallBuilder($schemaFactory), $schemaFactory, $cfg, $events);
    $responseModel = $responseFactory->fromAny(NRAP_User::class);

    $inferenceProvider = new InferenceProvider($llm, new RequestMaterializer(), $events);

    $partialsFactory = new PartialStreamFactory(
        deserializer: new ResponseDeserializer($events, [\Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer::class], $cfg),
        validator: new PartialValidation(new \Cognesy\Instructor\Config\PartialsGeneratorConfig()),
        transformer: new ResponseTransformer($events, [], $cfg),
        events: $events,
    );

    $streamGen = new PartialStreamingUpdateGenerator(
        inferenceProvider: $inferenceProvider,
        partials: $partialsFactory,
    );

    $execution = (new StructuredOutputExecution())
        ->with(
            request: (new StructuredOutputRequest())
                ->withMessages([[ 'role' => 'user', 'content' => 'Test' ]])
                ->withStreamed(true),
            responseModel: $responseModel,
            config: (new StructuredOutputConfig())->with(outputMode: OutputMode::Json),
        );

    // First nextChunk initializes stream but does not consume all chunks
    $execution = $streamGen->nextChunk($execution);
    expect($execution->attemptState())->not->toBeNull();
    expect($execution->attemptState()->hasMoreChunks())->toBeTrue();

    // Process a single chunk and verify more remain (no full-buffering)
    $execution = $streamGen->nextChunk($execution);
    expect($execution->attemptState()->hasMoreChunks())->toBeTrue();
});
