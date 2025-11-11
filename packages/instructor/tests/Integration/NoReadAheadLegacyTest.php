<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Core\InferenceProvider;
use Cognesy\Instructor\Core\RequestMaterializer;
use Cognesy\Instructor\Creation\ResponseModelFactory;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\ResponseIterators\GeneratorBased\PartialGen\GeneratePartialsFromJson;
use Cognesy\Instructor\ResponseIterators\GeneratorBased\StreamingUpdatesGenerator;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Instructor\Validation\PartialValidation;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Schema\Factories\SchemaFactory;
use Cognesy\Schema\Factories\ToolCallBuilder;

require_once __DIR__ . '/Support/ProbeIterator.php';
require_once __DIR__ . '/Support/ProbeStreamDriver.php';

class NRAL_User { public string $name; public int $age; }

it('does not buffer whole stream on init in legacy pipeline', function () {
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
    $responseModel = $responseFactory->fromAny(NRAL_User::class);

    $inferenceProvider = new InferenceProvider($llm, new RequestMaterializer(), $events);

    $partialsGen = new GeneratePartialsFromJson(
        responseDeserializer: new ResponseDeserializer($events, [\Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer::class], $cfg),
        partialValidator: new PartialValidation(new \Cognesy\Instructor\Config\PartialsGeneratorConfig()),
        responseTransformer: new ResponseTransformer($events, [], $cfg),
        events: $events,
    );

    $streamGen = new StreamingUpdatesGenerator(
        inferenceProvider: $inferenceProvider,
        partialsGenerator: $partialsGen,
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

    // Process a chunk and verify we received an update (even if stream exhausts)
    $execution = $streamGen->nextChunk($execution);
    expect($execution->inferenceResponse())->not->toBeNull();
});
