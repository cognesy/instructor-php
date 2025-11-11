<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\PartialsGeneratorConfig;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Creation\ResponseModelFactory;
use Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\ResponseIterators\DecoratedPipeline\PartialStreamFactory;
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Instructor\Validation\PartialValidation;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Schema\Factories\SchemaFactory;
use Cognesy\Schema\Factories\ToolCallBuilder;

class SimpleTestData
{
    public function __construct(
        public string $value,
    ) {}
}

test('minimal pipeline processes single chunk', function() {
    $events = new EventDispatcher();
    $config = new StructuredOutputConfig();
    $partialsConfig = new PartialsGeneratorConfig();

    $deserializer = new ResponseDeserializer($events, [SymfonyDeserializer::class], $config);
    $validator = new PartialValidation($partialsConfig);
    $transformer = new ResponseTransformer(events: $events, transformers: [], config: $config);
    $factory = new PartialStreamFactory(
        deserializer: $deserializer,
        validator: $validator,
        transformer: $transformer,
        events: $events,
    );

    $schemaFactory = new SchemaFactory(useObjectReferences: $config->useObjectReferences());
    $responseModel = (new ResponseModelFactory(
        toolCallBuilder: new ToolCallBuilder($schemaFactory),
        schemaFactory: $schemaFactory,
        config: $config,
        events: $events,
    ))->fromAny(SimpleTestData::class);

    // Single complete JSON chunk
    $source = [
        new PartialInferenceResponse(
            contentDelta: '{"value": "test"}',
            usage: new Usage(inputTokens: 10, outputTokens: 5),
        ),
    ];

    $stream = $factory->makePureStream($source, $responseModel, OutputMode::JsonSchema);

    // Collect with timeout
    $results = [];
    $count = 0;
    foreach ($stream as $item) {
        $results[] = $item;
        $count++;
        if ($count > 10) { // Safety limit
            break;
        }
    }

    expect($results)->not()->toBeEmpty();
});
