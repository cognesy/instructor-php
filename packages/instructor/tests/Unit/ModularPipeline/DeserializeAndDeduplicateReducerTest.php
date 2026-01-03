<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Creation\ResponseModelFactory;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Extraction\Buffers\JsonBuffer;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\Domain\PartialFrame;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\Enums\EmissionType;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\Pipeline\DeserializeAndDeduplicateReducer;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Instructor\Validation\Contracts\CanValidatePartialResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Schema\Factories\SchemaFactory;
use Cognesy\Schema\Factories\ToolCallBuilder;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Utils\Result\Result;

class TestDeserializeModel {
    public string $key = '';
}

function makeDeserializeTestResponseModel(): ResponseModel {
    $cfg = new StructuredOutputConfig();
    $schemaFactory = new SchemaFactory(useObjectReferences: $cfg->useObjectReferences());
    $factory = new ResponseModelFactory(new ToolCallBuilder($schemaFactory), $schemaFactory, $cfg, new EventDispatcher());
    return $factory->fromAny(TestDeserializeModel::class);
}

function makeDeserializedCollector(): Reducer {
    return new class implements Reducer {
        public array $collected = [];

        public function init(): mixed {
            $this->collected = [];
            return null;
        }

        public function step(mixed $accumulator, mixed $reducible): mixed {
            $this->collected[] = $reducible;
            return $reducible;
        }

        public function complete(mixed $accumulator): mixed {
            return $this->collected;
        }
    };
}

function makeSuccessDeserializer(): CanDeserializeResponse {
    return new class implements CanDeserializeResponse {
        public function deserialize(array $data, ResponseModel $responseModel): Result {
            return Result::success((object) $data);
        }
    };
}

function makeFailureDeserializer(): CanDeserializeResponse {
    return new class implements CanDeserializeResponse {
        public function deserialize(array $data, ResponseModel $responseModel): Result {
            return Result::failure('Deserialization failed');
        }
    };
}

function makePassThroughValidator(): CanValidatePartialResponse {
    return new class implements CanValidatePartialResponse {
        public function validatePartialResponse(array $data, ResponseModel $responseModel): Result {
            return Result::success(null);
        }
    };
}

function makeFailingValidator(): CanValidatePartialResponse {
    return new class implements CanValidatePartialResponse {
        public function validatePartialResponse(array $data, ResponseModel $responseModel): Result {
            return Result::failure('Validation failed');
        }
    };
}

function makePassThroughTransformer(): CanTransformResponse {
    return new class implements CanTransformResponse {
        public function transform(mixed $object, ResponseModel $responseModel): Result {
            return Result::success($object);
        }
    };
}

test('deserializes valid JSON and marks for emission', function() {
    $collector = makeDeserializedCollector();
    $reducer = new DeserializeAndDeduplicateReducer(
        inner: $collector,
        deserializer: makeSuccessDeserializer(),
        validator: makePassThroughValidator(),
        transformer: makePassThroughTransformer(),
        responseModel: makeDeserializeTestResponseModel(),
    );

    $reducer->init();

    $frame = PartialFrame::fromResponse(new PartialInferenceResponse(usage: Usage::none()))
        ->withBuffer(JsonBuffer::empty()->assemble('{"key": "value"}'));

    $reducer->step(null, $frame);

    expect($collector->collected)->toHaveCount(1)
        ->and($collector->collected[0]->hasObject())->toBeTrue()
        ->and($collector->collected[0]->emissionType)->toBe(EmissionType::ObjectReady);
});

test('skips frames without content', function() {
    $collector = makeDeserializedCollector();
    $reducer = new DeserializeAndDeduplicateReducer(
        inner: $collector,
        deserializer: makeSuccessDeserializer(),
        validator: makePassThroughValidator(),
        transformer: makePassThroughTransformer(),
        responseModel: makeDeserializeTestResponseModel(),
    );

    $reducer->init();

    $frame = PartialFrame::fromResponse(new PartialInferenceResponse(usage: Usage::none()))
        ->withBuffer(JsonBuffer::empty());

    $reducer->step(null, $frame);

    // Frame forwarded without processing
    expect($collector->collected)->toHaveCount(1)
        ->and($collector->collected[0]->hasObject())->toBeFalse();
});

test('handles deserialization failure', function() {
    $collector = makeDeserializedCollector();
    $reducer = new DeserializeAndDeduplicateReducer(
        inner: $collector,
        deserializer: makeFailureDeserializer(),
        validator: makePassThroughValidator(),
        transformer: makePassThroughTransformer(),
        responseModel: makeDeserializeTestResponseModel(),
    );

    $reducer->init();

    $frame = PartialFrame::fromResponse(new PartialInferenceResponse(usage: Usage::none()))
        ->withBuffer(JsonBuffer::empty()->assemble('{"bad": "json"'));

    $reducer->step(null, $frame);

    expect($collector->collected)->toHaveCount(1)
        ->and($collector->collected[0]->isError())->toBeTrue()
        ->and($collector->collected[0]->emissionType)->toBe(EmissionType::None);
});

test('handles validation failure', function() {
    $collector = makeDeserializedCollector();
    $reducer = new DeserializeAndDeduplicateReducer(
        inner: $collector,
        deserializer: makeSuccessDeserializer(),
        validator: makeFailingValidator(),
        transformer: makePassThroughTransformer(),
        responseModel: makeDeserializeTestResponseModel(),
    );

    $reducer->init();

    $frame = PartialFrame::fromResponse(new PartialInferenceResponse(usage: Usage::none()))
        ->withBuffer(JsonBuffer::empty()->assemble('{"key": "value"}'));

    $reducer->step(null, $frame);

    expect($collector->collected)->toHaveCount(1)
        ->and($collector->collected[0]->isError())->toBeTrue();
});

test('deduplicates identical objects', function() {
    $collector = makeDeserializedCollector();
    $reducer = new DeserializeAndDeduplicateReducer(
        inner: $collector,
        deserializer: makeSuccessDeserializer(),
        validator: makePassThroughValidator(),
        transformer: makePassThroughTransformer(),
        responseModel: makeDeserializeTestResponseModel(),
    );

    $reducer->init();

    // First object
    $frame1 = PartialFrame::fromResponse(new PartialInferenceResponse(usage: Usage::none()))
        ->withBuffer(JsonBuffer::empty()->assemble('{"key": "value"}'));

    $reducer->step(null, $frame1);

    // Same object again
    $frame2 = PartialFrame::fromResponse(new PartialInferenceResponse(usage: Usage::none()))
        ->withBuffer(JsonBuffer::empty()->assemble('{"key": "value"}'));

    $reducer->step(null, $frame2);

    // First should emit, second should not
    expect($collector->collected[0]->emissionType)->toBe(EmissionType::ObjectReady)
        ->and($collector->collected[1]->emissionType)->toBe(EmissionType::None);
});

test('emits when object changes', function() {
    $collector = makeDeserializedCollector();
    $reducer = new DeserializeAndDeduplicateReducer(
        inner: $collector,
        deserializer: makeSuccessDeserializer(),
        validator: makePassThroughValidator(),
        transformer: makePassThroughTransformer(),
        responseModel: makeDeserializeTestResponseModel(),
    );

    $reducer->init();

    // First object
    $frame1 = PartialFrame::fromResponse(new PartialInferenceResponse(usage: Usage::none()))
        ->withBuffer(JsonBuffer::empty()->assemble('{"key": "value1"}'));

    $reducer->step(null, $frame1);

    // Different object
    $frame2 = PartialFrame::fromResponse(new PartialInferenceResponse(usage: Usage::none()))
        ->withBuffer(JsonBuffer::empty()->assemble('{"key": "value2"}'));

    $reducer->step(null, $frame2);

    // Both should emit
    expect($collector->collected[0]->emissionType)->toBe(EmissionType::ObjectReady)
        ->and($collector->collected[1]->emissionType)->toBe(EmissionType::ObjectReady);
});

test('init resets deduplication state', function() {
    $collector = makeDeserializedCollector();
    $reducer = new DeserializeAndDeduplicateReducer(
        inner: $collector,
        deserializer: makeSuccessDeserializer(),
        validator: makePassThroughValidator(),
        transformer: makePassThroughTransformer(),
        responseModel: makeDeserializeTestResponseModel(),
    );

    // First stream
    $reducer->init();
    $frame1 = PartialFrame::fromResponse(new PartialInferenceResponse(usage: Usage::none()))
        ->withBuffer(JsonBuffer::empty()->assemble('{"key": "value"}'));
    $reducer->step(null, $frame1);

    expect($collector->collected[0]->emissionType)->toBe(EmissionType::ObjectReady);

    // Reset for second stream
    $reducer->init();
    expect($collector->collected)->toBeEmpty();

    // Same object should emit again in new stream
    $frame2 = PartialFrame::fromResponse(new PartialInferenceResponse(usage: Usage::none()))
        ->withBuffer(JsonBuffer::empty()->assemble('{"key": "value"}'));
    $reducer->step(null, $frame2);

    expect($collector->collected[0]->emissionType)->toBe(EmissionType::ObjectReady);
});

test('preserves frame metadata through transformation', function() {
    $collector = makeDeserializedCollector();
    $reducer = new DeserializeAndDeduplicateReducer(
        inner: $collector,
        deserializer: makeSuccessDeserializer(),
        validator: makePassThroughValidator(),
        transformer: makePassThroughTransformer(),
        responseModel: makeDeserializeTestResponseModel(),
    );

    $reducer->init();

    $frame = PartialFrame::fromResponse(new PartialInferenceResponse(usage: Usage::none()), index: 5)
        ->withBuffer(JsonBuffer::empty()->assemble('{"key": "value"}'));

    $reducer->step(null, $frame);

    expect($collector->collected[0]->metadata->index)->toBe(5);
});

test('forwards errors without updating dedup state', function() {
    $collector = makeDeserializedCollector();
    $reducer = new DeserializeAndDeduplicateReducer(
        inner: $collector,
        deserializer: makeFailureDeserializer(),
        validator: makePassThroughValidator(),
        transformer: makePassThroughTransformer(),
        responseModel: makeDeserializeTestResponseModel(),
    );

    $reducer->init();

    // Frame with valid JSON that will fail deserialization
    $frame = PartialFrame::fromResponse(new PartialInferenceResponse(usage: Usage::none()))
        ->withBuffer(JsonBuffer::empty()->assemble('{"key": "value"}'));
    $reducer->step(null, $frame);

    // Should forward with error, no emission
    expect($collector->collected)->toHaveCount(1)
        ->and($collector->collected[0]->isError())->toBeTrue()
        ->and($collector->collected[0]->emissionType)->toBe(EmissionType::None);
});