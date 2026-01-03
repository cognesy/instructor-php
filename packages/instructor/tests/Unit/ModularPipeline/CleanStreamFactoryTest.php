<?php declare(strict_types=1);

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Creation\ResponseModelFactory;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\ModularStreamFactory;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Instructor\Validation\Contracts\CanValidatePartialResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Schema\Factories\SchemaFactory;
use Cognesy\Schema\Factories\ToolCallBuilder;
use Cognesy\Utils\Result\Result;

class TestFactoryModel {
    public string $test = '';
}

function makeFactoryTestResponseModel(): ResponseModel {
    $cfg = new StructuredOutputConfig();
    $schemaFactory = new SchemaFactory(useObjectReferences: $cfg->useObjectReferences());
    $factory = new ResponseModelFactory(new ToolCallBuilder($schemaFactory), $schemaFactory, $cfg, new EventDispatcher());
    return $factory->fromAny(TestFactoryModel::class);
}

function makeStreamDeserializer(): CanDeserializeResponse {
    return new class implements CanDeserializeResponse {
        public function deserialize(array $data, ResponseModel $responseModel): Result {
            return Result::success((object) $data);
        }
    };
}

function makeStreamValidator(): CanValidatePartialResponse {
    return new class implements CanValidatePartialResponse {
        public function validatePartialResponse(array $data, ResponseModel $responseModel): Result {
            return Result::success(null);
        }
    };
}

function makeStreamTransformer(): CanTransformResponse {
    return new class implements CanTransformResponse {
        public function transform(mixed $object, ResponseModel $responseModel): Result {
            return Result::success($object);
        }
    };
}

function makeStreamEvents(): CanHandleEvents {
    return new class implements CanHandleEvents {
        public array $events = [];

        public function dispatch(object $event): object {
            $this->events[] = $event;
            return $event;
        }

        public function wiretap(callable $callable): void {
        }

        public function getListenersForEvent(object $event): iterable {
            return [];
        }

        public function addListener(string $name, callable $listener, int $priority = 0): void {
        }
    };
}

test('creates observable stream from iterable source', function() {
    $factory = new ModularStreamFactory(
        deserializer: makeStreamDeserializer(),
        validator: makeStreamValidator(),
        transformer: makeStreamTransformer(),
        events: makeStreamEvents(),
    );

    $source = [
        new PartialInferenceResponse(contentDelta: '{"key"', usage: Usage::none()),
        new PartialInferenceResponse(contentDelta: ': "val', usage: Usage::none()),
        new PartialInferenceResponse(contentDelta: 'ue"}', usage: Usage::none()),
    ];

    $stream = $factory->makeStream(
        source: $source,
        responseModel: makeFactoryTestResponseModel(),
        mode: OutputMode::JsonSchema,
    );

    expect($stream)->toBeInstanceOf(IteratorAggregate::class);
});

test('stream processes all source items', function() {
    $factory = new ModularStreamFactory(
        deserializer: makeStreamDeserializer(),
        validator: makeStreamValidator(),
        transformer: makeStreamTransformer(),
        events: makeStreamEvents(),
    );

    $source = [
        new PartialInferenceResponse(contentDelta: '{"a": 1}', usage: Usage::none()),
        new PartialInferenceResponse(contentDelta: '{"b": 2}', usage: Usage::none()),
        new PartialInferenceResponse(contentDelta: '{"c": 3}', usage: Usage::none()),
    ];

    $stream = $factory->makeStream(
        source: $source,
        responseModel: makeFactoryTestResponseModel(),
        mode: OutputMode::JsonSchema,
    );

    $results = iterator_to_array($stream);

    expect($results)->toBeArray();
});

test('stream accumulates partials when enabled', function() {
    $factory = new ModularStreamFactory(
        deserializer: makeStreamDeserializer(),
        validator: makeStreamValidator(),
        transformer: makeStreamTransformer(),
        events: makeStreamEvents(),
    );

    // Simulate incremental JSON streaming like real API
    $source = [
        new PartialInferenceResponse(contentDelta: '{"test', usage: Usage::none()),
        new PartialInferenceResponse(contentDelta: '": "val', usage: Usage::none()),
        new PartialInferenceResponse(contentDelta: 'ue1"}', finishReason: 'stop', usage: Usage::none()),
    ];

    $stream = $factory->makeStream(
        source: $source,
        responseModel: makeFactoryTestResponseModel(),
        mode: OutputMode::JsonSchema,
        accumulatePartials: true,
    );

    $results = iterator_to_array($stream);

    expect($results)->not()->toBeEmpty();

    $final = end($results);
    expect($final)->toBeInstanceOf(\Cognesy\Instructor\ResponseIterators\ModularPipeline\Aggregation\StreamAggregate::class)
        ->and($final->partials)->not()->toBeNull()
        ->and($final->partials->count())->toBeGreaterThan(0);
})->skip('Integration test - better suited for Feature tests with FakeInferenceDriver');

test('stream does not accumulate partials when disabled', function() {
    $factory = new ModularStreamFactory(
        deserializer: makeStreamDeserializer(),
        validator: makeStreamValidator(),
        transformer: makeStreamTransformer(),
        events: makeStreamEvents(),
    );

    $source = [
        new PartialInferenceResponse(contentDelta: '{"a": 1}', usage: Usage::none()),
        new PartialInferenceResponse(contentDelta: '{"b": 2}', usage: Usage::none()),
    ];

    $stream = $factory->makeStream(
        source: $source,
        responseModel: makeFactoryTestResponseModel(),
        mode: OutputMode::JsonSchema,
        accumulatePartials: false,
    );

    $results = iterator_to_array($stream);
    $final = end($results);

    expect($final->partials)->toBeNull();
})->skip('Integration test - better suited for Feature tests with FakeInferenceDriver');

test('handles JsonSchema output mode', function() {
    $factory = new ModularStreamFactory(
        deserializer: makeStreamDeserializer(),
        validator: makeStreamValidator(),
        transformer: makeStreamTransformer(),
        events: makeStreamEvents(),
    );

    $source = [
        new PartialInferenceResponse(contentDelta: '{"test": true}', usage: Usage::none()),
    ];

    $stream = $factory->makeStream(
        source: $source,
        responseModel: makeFactoryTestResponseModel(),
        mode: OutputMode::JsonSchema,
    );

    $results = iterator_to_array($stream);

    expect($results)->toBeArray();
});

test('handles Tools output mode', function() {
    $factory = new ModularStreamFactory(
        deserializer: makeStreamDeserializer(),
        validator: makeStreamValidator(),
        transformer: makeStreamTransformer(),
        events: makeStreamEvents(),
    );

    $source = [
        new PartialInferenceResponse(
            toolName: 'test_tool',
            toolArgs: '{"param": "value"}',
            usage: Usage::none()
        ),
    ];

    $stream = $factory->makeStream(
        source: $source,
        responseModel: makeFactoryTestResponseModel(),
        mode: OutputMode::Tools,
    );

    $results = iterator_to_array($stream);

    expect($results)->toBeArray()
        ->and($results)->not()->toBeEmpty();
})->skip('Integration test - better suited for Feature tests with FakeInferenceDriver');

test('stream accumulates usage across chunks', function() {
    $factory = new ModularStreamFactory(
        deserializer: makeStreamDeserializer(),
        validator: makeStreamValidator(),
        transformer: makeStreamTransformer(),
        events: makeStreamEvents(),
    );

    $source = [
        (new PartialInferenceResponse(
            contentDelta: '{"a": 1}',
            usage: new Usage(inputTokens: 10, outputTokens: 5)
        ))->withContent('{"a": 1}'),
        (new PartialInferenceResponse(
            contentDelta: '{"b": 2}',
            usage: new Usage(inputTokens: 15, outputTokens: 8)
        ))->withContent('{"a": 1}{"b": 2}'),
    ];

    $stream = $factory->makeStream(
        source: $source,
        responseModel: makeFactoryTestResponseModel(),
        mode: OutputMode::JsonSchema,
    );

    $results = iterator_to_array($stream);
    $final = end($results);

    expect($final)->toBeInstanceOf(\Cognesy\Instructor\ResponseIterators\ModularPipeline\Aggregation\StreamAggregate::class)
        ->and($final->usage->outputTokens)->toBeGreaterThan(5);
})->skip('Integration test - better suited for Feature tests with FakeInferenceDriver');

test('stream handles finish reason', function() {
    $factory = new ModularStreamFactory(
        deserializer: makeStreamDeserializer(),
        validator: makeStreamValidator(),
        transformer: makeStreamTransformer(),
        events: makeStreamEvents(),
    );

    $source = [
        (new PartialInferenceResponse(
            contentDelta: '{"test": true}',
            finishReason: 'stop',
            usage: Usage::none()
        ))->withContent('{"test": true}'),
    ];

    $stream = $factory->makeStream(
        source: $source,
        responseModel: makeFactoryTestResponseModel(),
        mode: OutputMode::JsonSchema,
    );

    $results = iterator_to_array($stream);
    $final = end($results);

    expect($final)->toBeInstanceOf(\Cognesy\Instructor\ResponseIterators\ModularPipeline\Aggregation\StreamAggregate::class)
        ->and($final->finishReason())->toBe('stop');
})->skip('Integration test - better suited for Feature tests with FakeInferenceDriver');

test('factory uses provided deserializer', function() {
    $customDeserializer = new class implements CanDeserializeResponse {
        public bool $called = false;

        public function deserialize(array $data, ResponseModel $responseModel): Result {
            $this->called = true;
            return Result::success((object) $data);
        }
    };

    $factory = new ModularStreamFactory(
        deserializer: $customDeserializer,
        validator: makeStreamValidator(),
        transformer: makeStreamTransformer(),
        events: makeStreamEvents(),
    );

    $source = [
        new PartialInferenceResponse(contentDelta: '{"test": true}', usage: Usage::none()),
    ];

    $stream = $factory->makeStream(
        source: $source,
        responseModel: makeFactoryTestResponseModel(),
        mode: OutputMode::JsonSchema,
    );

    iterator_to_array($stream);

    expect($customDeserializer->called)->toBeTrue();
});

test('factory uses provided validator', function() {
    $customValidator = new class implements CanValidatePartialResponse {
        public bool $called = false;

        public function validatePartialResponse(array $data, ResponseModel $responseModel): Result {
            $this->called = true;
            return Result::success(null);
        }
    };

    $factory = new ModularStreamFactory(
        deserializer: makeStreamDeserializer(),
        validator: $customValidator,
        transformer: makeStreamTransformer(),
        events: makeStreamEvents(),
    );

    $source = [
        new PartialInferenceResponse(contentDelta: '{"test": true}', usage: Usage::none()),
    ];

    $stream = $factory->makeStream(
        source: $source,
        responseModel: makeFactoryTestResponseModel(),
        mode: OutputMode::JsonSchema,
    );

    iterator_to_array($stream);

    expect($customValidator->called)->toBeTrue();
});

test('factory uses provided event handler', function() {
    $customEvents = new class implements CanHandleEvents {
        public array $events = [];

        public function dispatch(object $event): object {
            $this->events[] = $event;
            return $event;
        }

        public function wiretap(callable $callable): void {
        }

        public function getListenersForEvent(object $event): iterable {
            return [];
        }

        public function addListener(string $name, callable $listener, int $priority = 0): void {
        }
    };

    $factory = new ModularStreamFactory(
        deserializer: makeStreamDeserializer(),
        validator: makeStreamValidator(),
        transformer: makeStreamTransformer(),
        events: $customEvents,
    );

    $source = [
        new PartialInferenceResponse(contentDelta: '{"test": true}', usage: Usage::none()),
    ];

    $stream = $factory->makeStream(
        source: $source,
        responseModel: makeFactoryTestResponseModel(),
        mode: OutputMode::JsonSchema,
    );

    iterator_to_array($stream);

    expect($customEvents->events)->not()->toBeEmpty();
});


