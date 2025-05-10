<?php

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\Request\SequenceUpdated;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\Features\Core\PartialsGenerator;
use Cognesy\Instructor\Features\Core\ResponseModelFactory;
use Cognesy\Instructor\Features\Deserialization\Deserializers\SymfonyDeserializer;
use Cognesy\Instructor\Features\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Features\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Features\Schema\Factories\ToolCallBuilder;
use Cognesy\Instructor\Features\Schema\Utils\ReferenceQueue;
use Cognesy\Instructor\Features\Transformation\ResponseTransformer;
use Cognesy\Polyglot\LLM\Data\PartialLLMResponse;
use Cognesy\Utils\Events\EventDispatcher;

class SimpleItem
{
    public function __construct(
        public string $name,
        public int    $value
    ) {}
}

function createStreamingGenerator(array $chunks): Generator {
    foreach ($chunks as $chunk) {
        yield new PartialLLMResponse(contentDelta: $chunk);
    }
}

function makeResponseModel($sequence): ResponseModel {
    return (new ResponseModelFactory(
        toolCallBuilder: new ToolCallBuilder(
            new SchemaFactory(),
            new ReferenceQueue(),
        ),
        schemaFactory: new SchemaFactory(),
        events: new EventDispatcher(),
    ))->fromAny($sequence);
}

beforeEach(function () {
    $this->events = Mockery::mock(EventDispatcher::class);
    $this->events->shouldReceive('dispatch')->byDefault();

    $this->deserializer = new ResponseDeserializer($this->events, [SymfonyDeserializer::class]);
    $this->transformer = new ResponseTransformer($this->events, []);

    $this->generator = new PartialsGenerator(
        $this->deserializer,
        $this->transformer,
        $this->events
    );
});

test('processes content streamed character by character', function() {
    // Create a JSON string that we'll stream character by character
    $jsonString = "Here's items:\n\n```json\n{\"list\": [" .
        "{\n\"name\": \"char\",\n\"value\": 1},\n" .
        "{\n\"name\": \"by\",\n\"value\": 2},\n" .
        "{\n\"name\": \"char\",\n\"value\": 3}\n" .
        "]}\n```\n";

    // Split the string into individual characters
    $chunks = str_split($jsonString);

    $sequence = Sequence::of(SimpleItem::class);
    $responseModel = makeResponseModel($sequence);

    $updates = [];
    $this->events->shouldReceive('dispatch')
        ->with(Mockery::on(function($event) use (&$updates) {
            if ($event instanceof SequenceUpdated) {
                $updates[] = $event->sequence->toArray();
                return true;
            }
            return true;
        }));

    $stream = createStreamingGenerator($chunks);
    $results = iterator_to_array($this->generator->getPartialResponses($stream, $responseModel));

    // We should get an update for each complete item
    expect($updates)->toHaveCount(3)
        // First update: first item added
        ->and($updates[0])->toHaveCount(1)
        ->and($updates[0][0]->name)->toBe('char')
        ->and($updates[0][0]->value)->toBe(1)
        // Second update: second item added
        ->and($updates[1])->toHaveCount(2)
        ->and($updates[1][1]->name)->toBe('by')
        ->and($updates[1][1]->value)->toBe(2)
        // Third update: third item added
        ->and($updates[2])->toHaveCount(3)
        ->and($updates[2][2]->name)->toBe('char')
        ->and($updates[2][2]->value)->toBe(3);
});

test('processes multiple items per chunk', function() {
    // Two chunks, each containing two complete items
    $chunks = [
        "Here's the first batch:\n\n```json\n{\"list\": [" .
        "{\n\"name\": \"batch1_item1\",\n\"value\": 1},\n" .
        "{\n\"name\": \"batch1_item2\",\n\"value\": 2}",

        ",\n" .
        "{\n\"name\": \"batch2_item1\",\n\"value\": 3},\n" .
        "{\n\"name\": \"batch2_item2\",\n\"value\": 4}\n" .
        "]}\n```\n"
    ];

    $sequence = Sequence::of(SimpleItem::class);
    $responseModel = makeResponseModel($sequence);

    $updates = [];
    $this->events->shouldReceive('dispatch')
        ->with(Mockery::on(function($event) use (&$updates) {
            if ($event instanceof SequenceUpdated) {
                $updates[] = $event->sequence->toArray();
                return true;
            }
            return true;
        }));

    $stream = createStreamingGenerator($chunks);
    $results = iterator_to_array($this->generator->getPartialResponses($stream, $responseModel));

    // We should get updates for each chunk that adds items
    expect($updates)->toHaveCount(4)
        // First update: 1 item
        ->and($updates[0])->toHaveCount(1)
        ->and($updates[0][0]->name)->toBe('batch1_item1')
        ->and($updates[0][0]->value)->toBe(1)
        // Second update: 2 items
        ->and($updates[1])->toHaveCount(2)
        ->and($updates[1][1]->name)->toBe('batch1_item2')
        ->and($updates[1][1]->value)->toBe(2)
        // Third update: 3 items
        ->and($updates[2])->toHaveCount(3)
        ->and($updates[2][2]->name)->toBe('batch2_item1')
        ->and($updates[2][2]->value)->toBe(3)
        // Fourth update: 4 items
        ->and($updates[3])->toHaveCount(4)
        ->and($updates[3][3]->name)->toBe('batch2_item2')
        ->and($updates[3][3]->value)->toBe(4);
});

test('processes streaming markdown with json into sequence correctly', function () {
    // Prepare test data - simulating markdown with JSON arriving in chunks
    $chunks = [
        "Here's a list of items:\n\n```json\n{\"list\": [",
        "{\n\"name\": \"first\",\n\"value\": 1",
        "}",
        ",",
        "{\n\"name\": \"second\",\n\"value\": 2}",
        "]}",
        "\n```\n\nEnd of list."
    ];

    // Set up response model for a sequence of SimpleItem
    $sequence = Sequence::of(SimpleItem::class, 'TestSequence', 'A test sequence');
    $responseModel = makeResponseModel($sequence);

    // Track sequence updates
    $updates = [];
    $this->events->shouldReceive('dispatch')
        ->with(Mockery::on(function ($event) use (&$updates) {
            if ($event instanceof SequenceUpdated) {
                $updates[] = $event->sequence->toArray();
                return true;
            }
            return true;
        }));

    // Process the stream
    $stream = createStreamingGenerator($chunks);
    $results = iterator_to_array($this->generator->getPartialResponses($stream, $responseModel));

    // Verify sequence updates occurred correctly
    expect($updates)->toHaveCount(2)
        ->and($updates[0])->toHaveCount(1)
        ->and($updates[0][0]->name)->toBe('first')
        ->and($updates[0][0]->value)->toBe(1)
        ->and($updates[1])->toHaveCount(2)
        ->and($updates[1][1]->name)->toBe('second')
        ->and($updates[1][1]->value)->toBe(2);
});

//test('processes streaming markdown with json into sequence correctly', function() {
//    $chunks = [
//        "Here's a list of items:\n\n```json\n{\"list\": [",
//        "{\n\"name\": \"first\",\n\"value\": 1",
//        "}",
//        ",",
//        "{\n\"name\": \"second\",\n\"value\": 2}",
//        "]}",
//        "\n```\n\nEnd of list."
//    ];
//
//    $sequence = Sequence::of(SimpleItem::class, 'TestSequence', 'A test sequence');
//    $responseModel = makeResponseModel($sequence);
//
//    $updates = [];
//    $this->events->shouldReceive('dispatch')
//        ->with(Mockery::on(function($event) use (&$updates) {
//            if ($event instanceof SequenceUpdated) {
//                $updates[] = $event->sequence->toArray();
//                return true;
//            }
//            return true;
//        }));
//
//    $stream = createStreamingGenerator($chunks);
//    $results = iterator_to_array($this->generator->getPartialResponses($stream, $responseModel));
//
//    expect($updates)->toHaveCount(2)
//        // First update: sequence with only first item
//        ->and($updates[0])->toHaveCount(1)
//        ->and($updates[0][0]->name)->toBe('first')
//        ->and($updates[0][0]->value)->toBe(1)
//        // Second update: sequence with second item added
//        ->and($updates[1])->toHaveCount(2)
//        ->and($updates[1][1]->name)->toBe('second')
//        ->and($updates[1][1]->value)->toBe(2);
//});

test('handles malformed json in stream gracefully', function () {
    $chunks = [
        "Here's a list:\n\n```json\n{\"list\": [",
        "{\n\"name\": \"first\",\n\"value\": 1}",
        ",{\"name\": \"second\"", // Missing required value field
        ",\"invalid\": true}", // Invalid JSON structure
        "]}",
        "\n```\n"
    ];

    $sequence = Sequence::of(SimpleItem::class);
    $responseModel = makeResponseModel($sequence);

    $updates = [];
    $this->events->shouldReceive('dispatch')
        ->with(Mockery::on(function ($event) use (&$updates) {
            if ($event instanceof SequenceUpdated) {
                $updates[] = $event->sequence->toArray();
                return true;
            }
            return true;
        }));

    $stream = createStreamingGenerator($chunks);
    $results = iterator_to_array($this->generator->getPartialResponses($stream, $responseModel));

    // Should only have processed the valid first item
    expect($updates)->toHaveCount(1)
        ->and($updates[0])->toHaveCount(1)
        ->and($updates[0][0]->name)->toBe('first')
        ->and($updates[0][0]->value)->toBe(1);
});

test('processes concatenated json blocks in markdown stream', function () {
    $chunks = [
        "First and second items:\n\n```json\n{\"list\": [",
        "{\n\"name\": \"item1\",\n\"value\": 1},",
        "{\n\"name\": \"item2\",\n\"value\": 2}",
        "]}\n```\n"
    ];

    $sequence = Sequence::of(SimpleItem::class);
    $responseModel = makeResponseModel($sequence);

    $updates = [];
    $this->events->shouldReceive('dispatch')
        ->with(Mockery::on(function ($event) use (&$updates) {
            if ($event instanceof SequenceUpdated) {
                $updates[] = $event->sequence->toArray();
                return true;
            }
            return true;
        }));

    $stream = createStreamingGenerator($chunks);
    $results = iterator_to_array($this->generator->getPartialResponses($stream, $responseModel));

    expect($updates)->toHaveCount(2)
        ->and($updates[0])->toHaveCount(1)
        ->and($updates[0][0]->name)->toBe('item1')
        ->and($updates[0][0]->value)->toBe(1)
        ->and($updates[1])->toHaveCount(2)
        ->and($updates[1][1]->name)->toBe('item2')
        ->and($updates[1][1]->value)->toBe(2);
});

test('handles empty and whitespace chunks in stream', function () {
    $chunks = [
        "List:\n\n```json\n",
        "  ", // Whitespace chunk
        "{\"list\": [",
        "", // Empty chunk
        "{\n\"name\": \"test\",\n\"value\": 1}",
        "]}",
        "\n```"
    ];

    $sequence = Sequence::of(SimpleItem::class);
    $responseModel = makeResponseModel($sequence);

    $updates = [];
    $this->events->shouldReceive('dispatch')
        ->with(Mockery::on(function ($event) use (&$updates) {
            if ($event instanceof SequenceUpdated) {
                $updates[] = $event->sequence->toArray();
                return true;
            }
            return true;
        }));

    $stream = createStreamingGenerator($chunks);
    $results = iterator_to_array($this->generator->getPartialResponses($stream, $responseModel));

    expect($updates)->toHaveCount(1)
        ->and($updates[0])->toHaveCount(1)
        ->and($updates[0][0]->name)->toBe('test')
        ->and($updates[0][0]->value)->toBe(1);
});

test('processes multiple items arriving in single chunk', function() {
    // Two items arriving in a single chunk
    $chunks = [
        "Here's multiple items:\n\n```json\n{\"list\": [" .
        "{\n\"name\": \"item1\",\n\"value\": 1},\n" .
        "{\n\"name\": \"item2\",\n\"value\": 2}" .
        "]}\n```\n"
    ];

    $sequence = Sequence::of(SimpleItem::class);
    $responseModel = makeResponseModel($sequence);

    $dispatchCount = 0;
    $updates = [];
    $this->events->shouldReceive('dispatch')
        ->with(Mockery::on(function($event) use (&$updates, &$dispatchCount) {
            if ($event instanceof SequenceUpdated) {
                $updates[] = $event->sequence->toArray();
                $dispatchCount++;
                return true;
            }
            return true;
        }));

    $stream = createStreamingGenerator($chunks);
    $results = iterator_to_array($this->generator->getPartialResponses($stream, $responseModel));

    // Should get two separate updates, one per item
    expect($updates)->toHaveCount(2)
        // First update: first item only
        ->and($updates[0])->toHaveCount(1)
        ->and($updates[0][0]->name)->toBe('item1')
        ->and($updates[0][0]->value)->toBe(1)
        // Second update: both items
        ->and($updates[1])->toHaveCount(2)
        ->and($updates[1][1]->name)->toBe('item2')
        ->and($updates[1][1]->value)->toBe(2);
});

test('processes items arriving across multiple chunks', function() {
    $chunks = [
        "First batch:\n\n```json\n{\"list\": [" .
        "{\n\"name\": \"batch1_item1\",\n\"value\": 1},\n" .
        "{\n\"name\": \"batch1_item2\",\n\"value\": 2}",

        ",\n" .
        "{\n\"name\": \"batch2_item1\",\n\"value\": 3},\n" .
        "{\n\"name\": \"batch2_item2\",\n\"value\": 4}\n" .
        "]}\n```\n"
    ];

    $sequence = Sequence::of(SimpleItem::class);
    $responseModel = makeResponseModel($sequence);

    $updates = [];
    $this->events->shouldReceive('dispatch')
        ->with(Mockery::on(function($event) use (&$updates) {
            if ($event instanceof SequenceUpdated) {
                $updates[] = $event->sequence->toArray();
                return true;
            }
            return true;
        }));

    $stream = createStreamingGenerator($chunks);
    $results = iterator_to_array($this->generator->getPartialResponses($stream, $responseModel));

    // Should get four separate updates, one per item
    expect($updates)->toHaveCount(4)
        // First update: 1 item
        ->and($updates[0])->toHaveCount(1)
        ->and($updates[0][0]->name)->toBe('batch1_item1')
        ->and($updates[0][0]->value)->toBe(1)
        // Second update: 2 items
        ->and($updates[1])->toHaveCount(2)
        ->and($updates[1][1]->name)->toBe('batch1_item2')
        ->and($updates[1][1]->value)->toBe(2)
        // Third update: 3 items
        ->and($updates[2])->toHaveCount(3)
        ->and($updates[2][2]->name)->toBe('batch2_item1')
        ->and($updates[2][2]->value)->toBe(3)
        // Fourth update: 4 items
        ->and($updates[3])->toHaveCount(4)
        ->and($updates[3][3]->name)->toBe('batch2_item2')
        ->and($updates[3][3]->value)->toBe(4);
});
