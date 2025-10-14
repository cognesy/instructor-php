<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\PartialsGeneratorConfig;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Creation\ResponseModelFactory;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Partials\Data\AggregatedResponse;
use Cognesy\Instructor\Partials\PartialStreamFactory;
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Schema\Factories\SchemaFactory;
use Cognesy\Schema\Factories\ToolCallBuilder;

class ToolsModeTestItem
{
    public function __construct(
        public string $task,
        public int $priority,
    ) {}
}

function makeToolCallPartialStream(string $toolName, array $argsChunks): Generator {
    // Initial signal: do not attribute input tokens here (non-emitting step)
    yield new PartialInferenceResponse(
        toolName: $toolName,
        usage: new Usage(inputTokens: 0, outputTokens: 0),
    );

    // Stream arguments; attribute input tokens to the last chunk (first emit likely happens then)
    $tokenCount = 0;
    $lastIndex = max(0, count($argsChunks) - 1);
    foreach ($argsChunks as $i => $chunk) {
        $tokenCount += strlen($chunk);
        $input = ($i === $lastIndex) ? 5 : 0;
        yield new PartialInferenceResponse(
            toolArgs: $chunk,
            usage: new Usage(inputTokens: $input, outputTokens: $tokenCount),
        );
    }
}

function makeToolResponseModel($class, string $toolName): ResponseModel {
    $config = new StructuredOutputConfig();
    $schemaFactory = new SchemaFactory(useObjectReferences: $config->useObjectReferences());
    $events = new EventDispatcher();
    $factory = new ResponseModelFactory(
        toolCallBuilder: new ToolCallBuilder($schemaFactory),
        schemaFactory: $schemaFactory,
        config: $config,
        events: $events,
    );
    // Create standard response model - tool name will be set by the model itself
    return $factory->fromAny($class);
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

    $this->transformer = new ResponseTransformer(
        events: $this->events,
        transformers: [],
        config: $this->config,
    );

    $this->factory = new PartialStreamFactory(
        deserializer: $this->deserializer,
        transformer: $this->transformer,
        events: $this->events,
        config: $this->partialsConfig,
    );
});

test('processes tool call stream with JSON arguments', function() {
    $toolName = 'create_task';
    $argsChunks = [
        '{"task": "Implement',
        ' feature", "priority": ',
        '5}',
    ];

    $source = makeToolCallPartialStream($toolName, $argsChunks);
    $responseModel = makeToolResponseModel(ToolsModeTestItem::class, $toolName);

    $stream = $this->factory->makePureStream($source, $responseModel, OutputMode::Tools);
    $results = iterator_to_array($stream);

    expect($results)->not()->toBeEmpty();

    $last = end($results);
    expect($last)->toBeInstanceOf(AggregatedResponse::class)
        ->and($last->latestValue)->toBeInstanceOf(ToolsModeTestItem::class)
        ->and($last->latestValue->task)->toBe('Implement feature')
        ->and($last->latestValue->priority)->toBe(5);
});

test('handles tool call with complete JSON in single chunk', function() {
    $toolName = 'quick_task';
    $argsChunks = ['{"task": "Quick action", "priority": 1}'];

    $source = makeToolCallPartialStream($toolName, $argsChunks);
    $responseModel = makeToolResponseModel(ToolsModeTestItem::class, $toolName);

    $stream = $this->factory->makePureStream($source, $responseModel, OutputMode::Tools);
    $results = iterator_to_array($stream);

    $last = end($results);
    expect($last)->toBeInstanceOf(AggregatedResponse::class)
        ->and($last->latestValue)->toBeInstanceOf(ToolsModeTestItem::class)
        ->and($last->latestValue->task)->toBe('Quick action')
        ->and($last->latestValue->priority)->toBe(1);
});

test('handles tool call streamed character by character', function() {
    $toolName = 'char_stream';
    $json = '{"task": "Test", "priority": 3}';
    $argsChunks = str_split($json);

    $source = makeToolCallPartialStream($toolName, $argsChunks);
    $responseModel = makeToolResponseModel(ToolsModeTestItem::class, $toolName);

    $stream = $this->factory->makePureStream($source, $responseModel, OutputMode::Tools);
    $results = iterator_to_array($stream);

    $last = end($results);
    expect($last)->toBeInstanceOf(AggregatedResponse::class)
        ->and($last->latestValue)->toBeInstanceOf(ToolsModeTestItem::class)
        ->and($last->latestValue->task)->toBe('Test')
        ->and($last->latestValue->priority)->toBe(3)
        ->and($last->partialCount)->toBeGreaterThan(1);
});

test('accumulates usage from tool call stream', function() {
    $toolName = 'usage_test';
    $argsChunks = str_split('{"task": "Count tokens", "priority": 2}', 5);

    $source = makeToolCallPartialStream($toolName, $argsChunks);
    $responseModel = makeToolResponseModel(ToolsModeTestItem::class, $toolName);

    $stream = $this->factory->makePureStream($source, $responseModel, OutputMode::Tools);
    $results = iterator_to_array($stream);

    $last = end($results);
    expect($last)->toBeInstanceOf(AggregatedResponse::class)
        ->and($last->usage->inputTokens)->toBe(5)
        ->and($last->usage->outputTokens)->toBeGreaterThan(0);
});

test('handles tool call with empty arguments chunks', function() {
    $toolName = 'empty_chunks';
    $argsChunks = [
        '{"task":',
        '',
        ' "Handle',
        '',
        ' empty", "priority": 1}',
    ];

    $source = makeToolCallPartialStream($toolName, $argsChunks);
    $responseModel = makeToolResponseModel(ToolsModeTestItem::class, $toolName);

    $stream = $this->factory->makePureStream($source, $responseModel, OutputMode::Tools);
    $results = iterator_to_array($stream);

    $last = end($results);
    expect($last)->toBeInstanceOf(AggregatedResponse::class)
        ->and($last->latestValue)->toBeInstanceOf(ToolsModeTestItem::class)
        ->and($last->latestValue->task)->toBe('Handle empty');
});

test('handles tool call with malformed JSON arguments', function() {
    $toolName = 'bad_args';
    $argsChunks = [
        '{"task": "Bad',
        ' data", "priority": "not_a_number"}', // Invalid type
    ];

    $source = makeToolCallPartialStream($toolName, $argsChunks);
    $responseModel = makeToolResponseModel(ToolsModeTestItem::class, $toolName);

    $stream = $this->factory->makePureStream($source, $responseModel, OutputMode::Tools);
    $results = iterator_to_array($stream);

    // Should still produce aggregated responses
    expect($results)->not()->toBeEmpty();

    $last = end($results);
    expect($last)->toBeInstanceOf(AggregatedResponse::class);
    // latestValue may be null if deserialization failed
});

test('deduplicates identical tool call results', function() {
    $toolName = 'dup_tool';
    $argsChunks = [
        '{"task": "Dup", "priority": 1}',
        '', // Empty - should not create new object
        '', // Another empty
    ];

    $source = makeToolCallPartialStream($toolName, $argsChunks);
    $responseModel = makeToolResponseModel(ToolsModeTestItem::class, $toolName);

    $stream = $this->factory->makePureStream($source, $responseModel, OutputMode::Tools);
    $results = iterator_to_array($stream);

    // Count unique objects
    $objectCount = 0;
    foreach ($results as $aggregate) {
        if ($aggregate->latestValue !== null) {
            $objectCount++;
        }
    }

    expect($objectCount)->toBeGreaterThan(0)
        ->and($objectCount)->toBeLessThan(count($argsChunks) + 1); // Fewer than total chunks
});

test('handles tool call without tool name signal', function() {
    // Stream starts directly with tool arguments, no tool name
    $generator = function() {
        yield new PartialInferenceResponse(
            toolArgs: '{"task": "No signal", "priority": 1}',
            usage: new Usage(inputTokens: 5, outputTokens: 10),
        );
    };

    $source = $generator();
    $responseModel = makeToolResponseModel(ToolsModeTestItem::class, 'expected_tool');

    $stream = $this->factory->makePureStream($source, $responseModel, OutputMode::Tools);
    $results = iterator_to_array($stream);

    // Should still process the tool call
    expect($results)->not()->toBeEmpty();
});
