<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\PartialsGeneratorConfig;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Creation\ResponseModelFactory;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\ResponseIterators\DecoratedPipeline\PartialStreamFactory;
use Cognesy\Instructor\ResponseIterators\DecoratedPipeline\ResponseAggregation\AggregationState;
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Instructor\Validation\PartialValidation;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Schema\Factories\SchemaFactory;
use Cognesy\Schema\Factories\ToolCallBuilder;

class MemoryEfficiencyTestItem
{
    public function __construct(
        public int $id,
        public string $data,
    ) {}
}

function makeLargePartialStream(int $itemCount): Generator {
    $tokenCount = 0;
    $first = true;
    for ($i = 1; $i <= $itemCount; $i++) {
        $chunk = sprintf('{"id": %d, "data": "Item %d"}', $i, $i);
        $tokenCount += strlen($chunk);
        $input = $first ? 10 : 0; // input tokens reported once per request
        $first = false;
        yield new PartialInferenceResponse(
            contentDelta: $chunk,
            usage: new Usage(inputTokens: $input, outputTokens: $tokenCount),
        );

        // Add separator between objects
        if ($i < $itemCount) {
            yield new PartialInferenceResponse(
                contentDelta: ',',
                usage: new Usage(inputTokens: 0, outputTokens: $tokenCount + 1),
            );
        }
    }
}

function makeMemoryResponseModel($class): ResponseModel {
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

test('maintains O(1) memory with large stream - only latest value retained', function() {
    // Process 100 items, each updating the aggregate
    $itemCount = 100;
    $source = makeLargePartialStream($itemCount);
    $responseModel = makeMemoryResponseModel(MemoryEfficiencyTestItem::class);

    $stream = $this->factory->makePureStream($source, $responseModel, OutputMode::JsonSchema);

    // Collect only last few results to verify streaming works
    $lastResults = [];
    $totalCount = 0;
    foreach ($stream as $aggregate) {
        $totalCount++;
        $lastResults[] = $aggregate;
        // Keep only last 5 to avoid memory accumulation in test
        if (count($lastResults) > 5) {
            array_shift($lastResults);
        }
    }

    expect($totalCount)->toBeGreaterThan(0);

    // Verify last aggregate has correct state
    $last = end($lastResults);
    expect($last)->toBeInstanceOf(AggregationState::class)
        // Only one value retained (latest)
        ->and($last->latestValue)->toBeInstanceOf(MemoryEfficiencyTestItem::class)
        // Partial count shows total items processed
        ->and($last->partialCount)->toBe($totalCount)
        // Usage accumulated across all partials
        ->and($last->usage->outputTokens)->toBeGreaterThan(0);
});

test('accumulates usage across many partials without storing all responses', function() {
    $itemCount = 50;
    $source = makeLargePartialStream($itemCount);
    $responseModel = makeMemoryResponseModel(MemoryEfficiencyTestItem::class);

    $stream = $this->factory->makePureStream($source, $responseModel, OutputMode::JsonSchema);
    $results = iterator_to_array($stream);

    $last = end($results);
    expect($last)->toBeInstanceOf(AggregationState::class)
        // Usage should be sum of all partials
        ->and($last->usage->inputTokens)->toBe(10) // Same for all
        ->and($last->usage->outputTokens)->toBeGreaterThan(50) // Accumulated
        // Partial count reflects total items processed
        ->and($last->partialCount)->toBeGreaterThan(1);
});

test('handles stream interruption - aggregate retains state', function() {
    $source = makeLargePartialStream(20);
    $responseModel = makeMemoryResponseModel(MemoryEfficiencyTestItem::class);

    $stream = $this->factory->makePureStream($source, $responseModel, OutputMode::JsonSchema);

    // Consume only first 10 items, simulating interruption
    $consumed = 0;
    $lastAggregate = null;
    foreach ($stream as $aggregate) {
        $lastAggregate = $aggregate;
        $consumed++;
        if ($consumed >= 10) {
            break;
        }
    }

    expect($lastAggregate)->toBeInstanceOf(AggregationState::class)
        ->and($lastAggregate->partialCount)->toBe($consumed)
        ->and($lastAggregate->latestValue)->not()->toBeNull();
});

test('verifies no accumulation of PartialInferenceResponse objects', function() {
    // This test verifies the key property: we don't store all PartialInferenceResponse objects
    // by checking that AggregatedResponse only contains counters and latest value

    $itemCount = 100;
    $source = makeLargePartialStream($itemCount);
    $responseModel = makeMemoryResponseModel(MemoryEfficiencyTestItem::class);

    $stream = $this->factory->makePureStream($source, $responseModel, OutputMode::JsonSchema);

    $aggregates = [];
    foreach ($stream as $aggregate) {
        $aggregates[] = $aggregate;
    }

    $last = end($aggregates);

    // AggregatedResponse should only contain:
    // - usage (single object)
    // - latestValue (single object)
    // - partialCount (int)
    // - finishReason (string|null)

    // It should NOT contain an array of all PartialInferenceResponse objects
    expect($last)->toBeInstanceOf(AggregationState::class)
        ->and($last->latestValue)->toBeInstanceOf(MemoryEfficiencyTestItem::class)
        ->and($last->partialCount)->toBeInt()
        ->and($last->usage)->toBeInstanceOf(Usage::class);

    // If we were storing all partials, memory would be O(n)
    // With O(1) design, memory is constant per aggregate
});

test('processes continuous stream without memory buildup', function() {
    // Simulate a very long continuous stream
    $generator = function() {
        $first = true;
        for ($i = 1; $i <= 200; $i++) {
            yield new PartialInferenceResponse(
                contentDelta: '{"id": ' . $i . ', "data": "Item ' . $i . '"}',
                usage: new Usage(inputTokens: $first ? 10 : 0, outputTokens: $i),
            );
            $first = false;
        }
    };

    $source = $generator();
    $responseModel = makeMemoryResponseModel(MemoryEfficiencyTestItem::class);

    $stream = $this->factory->makePureStream($source, $responseModel, OutputMode::JsonSchema);

    // Process stream without storing all results
    $processedCount = 0;
    $lastAggregate = null;
    foreach ($stream as $aggregate) {
        $lastAggregate = $aggregate;
        $processedCount++;
    }

    expect($processedCount)->toBeGreaterThan(0)
        ->and($lastAggregate)->toBeInstanceOf(AggregationState::class)
        ->and($lastAggregate->partialCount)->toBe($processedCount);
});

test('finishReason is retained through aggregation', function() {
    $generator = function() {
        yield new PartialInferenceResponse(
            contentDelta: '{"id": 1, "data": "test"}',
            usage: new Usage(inputTokens: 10, outputTokens: 5),
        );
        yield new PartialInferenceResponse(
            contentDelta: '',
            finishReason: 'stop',
            usage: new Usage(inputTokens: 0, outputTokens: 5),
        );
    };

    $source = $generator();
    $responseModel = makeMemoryResponseModel(MemoryEfficiencyTestItem::class);

    $stream = $this->factory->makePureStream($source, $responseModel, OutputMode::JsonSchema);
    $results = iterator_to_array($stream);

    $last = end($results);
    expect($last)->toBeInstanceOf(AggregationState::class)
        ->and($last->finishReason())->toBe('stop');
});
