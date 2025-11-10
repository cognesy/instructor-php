<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\PartialsGeneratorConfig;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Creation\ResponseModelFactory;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\ResponseIterators\Partials\PartialStreamFactory;
use Cognesy\Instructor\ResponseIterators\Partials\ResponseAggregation\AggregationState;
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Instructor\Validation\PartialValidation;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Schema\Factories\SchemaFactory;
use Cognesy\Schema\Factories\ToolCallBuilder;

class ContentModeTestItem
{
    public function __construct(
        public string $name,
        public int $value,
    ) {}
}

function makeContentModePartialStream(array $contentChunks): Generator {
    $tokenCount = 0;
    foreach ($contentChunks as $chunk) {
        $tokenCount += strlen($chunk);
        // Attribute per-request input tokens on every chunk; the pipeline
        // normalizes non-emitting steps to input=0, leaving only the emitting
        // step to contribute input tokens to the aggregate.
        yield new PartialInferenceResponse(
            contentDelta: $chunk,
            usage: new Usage(inputTokens: 10, outputTokens: $tokenCount),
        );
    }
}

function makeTestResponseModel($class): ResponseModel {
    $config = new StructuredOutputConfig();
    $schemaFactory = new SchemaFactory(useObjectReferences: $config->useObjectReferences());
    $events = new EventDispatcher();
    return (new ResponseModelFactory(
        toolCallBuilder: new ToolCallBuilder($schemaFactory),
        schemaFactory: $schemaFactory,
        config: $config,
        events: $events,
    ))->fromAny($class);
}

beforeEach(function () {
    $this->events = new EventDispatcher();
    $this->config = new StructuredOutputConfig();
    $this->partialsConfig = new PartialsGeneratorConfig();

    $this->deserializer = new ResponseDeserializer(
        $this->events,
        [SymfonyDeserializer::class],
        $this->config,
    );

    $this->validator = new PartialValidation($this->partialsConfig);

    $this->transformer = new ResponseTransformer(
        events: $this->events,
        transformers: [],
        config: $this->config,
    );

    $this->factory = new PartialStreamFactory(
        deserializer: $this->deserializer,
        validator: $this->validator,
        transformer: $this->transformer,
        events: $this->events,
    );
});

test('processes MdJson stream character by character', function() {
    // MdJson: markdown with fenced JSON
    $jsonContent = "Here's the data:\n\n```json\n{\"name\": \"test\", \"value\": 42}\n```\n";
    $chunks = str_split($jsonContent);

    $source = makeContentModePartialStream($chunks);
    $responseModel = makeTestResponseModel(ContentModeTestItem::class);

    $stream = $this->factory->makePureStream($source, $responseModel, OutputMode::MdJson);
    $results = iterator_to_array($stream);

    // Should get AggregatedResponse items
    expect($results)->not()->toBeEmpty();

    $last = end($results);
    expect($last)->toBeInstanceOf(AggregationState::class)
        ->and($last->latestValue)->toBeInstanceOf(ContentModeTestItem::class)
        ->and($last->latestValue->name)->toBe('test')
        ->and($last->latestValue->value)->toBe(42)
        ->and($last->partialCount)->toBeGreaterThanOrEqual(1); // At least one partial processed
});

test('processes JsonSchema stream with object', function() {
    // Pure JSON without markdown
    $jsonContent = '{"name": "direct", "value": 123}';
    $chunks = str_split($jsonContent, 5); // Split into 5-char chunks

    $source = makeContentModePartialStream($chunks);
    $responseModel = makeTestResponseModel(ContentModeTestItem::class);

    $stream = $this->factory->makePureStream($source, $responseModel, OutputMode::JsonSchema);
    $results = iterator_to_array($stream);

    $last = end($results);
    expect($last)->toBeInstanceOf(AggregationState::class)
        ->and($last->latestValue)->toBeInstanceOf(ContentModeTestItem::class)
        ->and($last->latestValue->name)->toBe('direct')
        ->and($last->latestValue->value)->toBe(123);
});

test('accumulates usage statistics correctly', function() {
    $chunks = str_split('{"name": "usage", "value": 999}', 5);
    $source = makeContentModePartialStream($chunks);
    $responseModel = makeTestResponseModel(ContentModeTestItem::class);

    $stream = $this->factory->makePureStream($source, $responseModel, OutputMode::JsonSchema);
    $results = iterator_to_array($stream);

    $last = end($results);
    expect($last)->toBeInstanceOf(AggregationState::class)
        ->and($last->usage->inputTokens)->toBe(10)
        ->and($last->usage->outputTokens)->toBeGreaterThan(0)
        ->and($last->partialCount)->toBeGreaterThan(1);
});

test('deduplicates identical objects', function() {
    // Stream chunks that produce the same JSON multiple times
    $chunks = [
        '{"name": "dup",',
        ' "value": 1}',
        '', // Empty chunk - should not produce new object
        '', // Another empty - should not produce new object
    ];

    $source = makeContentModePartialStream($chunks);
    $responseModel = makeTestResponseModel(ContentModeTestItem::class);

    $stream = $this->factory->makePureStream($source, $responseModel, OutputMode::JsonSchema);
    $results = iterator_to_array($stream);

    // Should only get unique objects, not duplicates
    $objectCount = 0;
    foreach ($results as $aggregate) {
        if ($aggregate->latestValue !== null) {
            $objectCount++;
        }
    }

    expect($objectCount)->toBeGreaterThan(0)
        ->and($objectCount)->toBeLessThan(count($chunks)); // Fewer objects than chunks due to deduplication
});

test('handles malformed JSON gracefully', function() {
    $chunks = [
        '{"name": "bad",',
        ' "value": "not_a_number"}', // Invalid - value should be int
    ];

    $source = makeContentModePartialStream($chunks);
    $responseModel = makeTestResponseModel(ContentModeTestItem::class);

    $stream = $this->factory->makePureStream($source, $responseModel, OutputMode::JsonSchema);
    $results = iterator_to_array($stream);

    // Should still produce aggregated responses even with errors
    expect($results)->not()->toBeEmpty();

    $last = end($results);
    expect($last)->toBeInstanceOf(AggregationState::class);
    // latestValue may be null if deserialization failed
});

test('processes sequence of items progressively', function() {
    $jsonContent = '{"list": [{"name": "first", "value": 1}, {"name": "second", "value": 2}]}';
    $chunks = str_split($jsonContent, 10);

    $sequence = Sequence::of(ContentModeTestItem::class);
    $responseModel = makeTestResponseModel($sequence);

    $source = makeContentModePartialStream($chunks);
    $stream = $this->factory->makePureStream($source, $responseModel, OutputMode::JsonSchema);
    $results = iterator_to_array($stream);

    $last = end($results);
    expect($last)->toBeInstanceOf(AggregationState::class)
        ->and($last->latestValue)->toBeInstanceOf(Sequence::class)
        ->and($last->latestValue->count())->toBe(2);
});

test('handles empty stream', function() {
    $source = makeContentModePartialStream([]);
    $responseModel = makeTestResponseModel(ContentModeTestItem::class);

    $stream = $this->factory->makePureStream($source, $responseModel, OutputMode::JsonSchema);
    $results = iterator_to_array($stream);

    expect($results)->toBeEmpty();
});

test('handles stream with only whitespace', function() {
    $chunks = ['   ', "\n", "\t", '  '];
    $source = makeContentModePartialStream($chunks);
    $responseModel = makeTestResponseModel(ContentModeTestItem::class);

    $stream = $this->factory->makePureStream($source, $responseModel, OutputMode::JsonSchema);
    $results = iterator_to_array($stream);

    // May be empty or have aggregates with null values
    expect($results)->toBeArray();
});
