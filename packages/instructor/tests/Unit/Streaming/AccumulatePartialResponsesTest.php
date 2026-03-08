<?php declare(strict_types=1);

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Streaming\StructuredOutputStreamState;
use Cognesy\Instructor\Streaming\Pipeline\AccumulatePartialResponses;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Stream\Transformation;
use Cognesy\Stream\TransformationStream;
use Cognesy\Utils\Result\Result;

final class AccumulatedUser
{
    public function __construct(
        public string $name,
        public int $age,
    ) {}
}

it('hydrates recoverable json partials and refines them as snapshots grow', function () {
    $responses = [
        new PartialInferenceDelta(contentDelta: '{"name"'),
        new PartialInferenceDelta(contentDelta: ':"Ann","age":30}'),
    ];

    $result = accumulateSnapshots(
        TransformationStream::from($responses)->using(Transformation::define(
            new AccumulatePartialResponses(
                mode: OutputMode::Json,
                deserializer: accumulatingDeserializer(),
                transformer: accumulatingTransformer(),
                responseModel: makeAnyResponseModel(AccumulatedUser::class),
            ),
        )),
    );

    expect($result)->toHaveCount(2);
    expect($result[0]->hasValue())->toBeTrue();
    expect($result[0]->value()->name)->toBe('');
    expect($result[0]->value()->age)->toBe(0);
    expect($result[1]->hasValue())->toBeTrue();
    expect($result[1]->value())->toBeInstanceOf(AccumulatedUser::class);
    expect($result[1]->value()->name)->toBe('Ann');
    expect($result[1]->value()->age)->toBe(30);
});

it('does not rehydrate unchanged snapshots repeatedly', function () {
    $calls = 0;
    $deserializer = new class($calls) implements CanDeserializeResponse {
        public function __construct(private int &$calls) {}

        public function deserialize(array $data, ResponseModel $responseModel): Result {
            $this->calls++;
            return Result::success($data);
        }
    };

    $responses = [
        new PartialInferenceDelta(contentDelta: '{"name":"Ann","age":30}'),
        new PartialInferenceDelta(finishReason: 'stop'),
    ];

    $result = accumulateSnapshots(
        TransformationStream::from($responses)->using(Transformation::define(
            new AccumulatePartialResponses(
                mode: OutputMode::Json,
                deserializer: $deserializer,
                transformer: accumulatingTransformer(),
                responseModel: makeAnyResponseModel(AccumulatedUser::class),
            ),
        )),
    );

    expect($calls)->toBe(1);
    expect($result)->toHaveCount(2);
    expect($result[0]->hasValue())->toBeTrue();
    expect($result[1]->hasValue())->toBeFalse();
});

it('hydrates recoverable tool argument partials and refines them as snapshots grow', function () {
    $responses = [
        new PartialInferenceDelta(toolId: 'tool-1', toolName: 'extract_data', toolArgs: '{"name"'),
        new PartialInferenceDelta(toolId: 'tool-1', toolName: 'extract_data', toolArgs: ':"Ann","age":30}'),
    ];

    $result = accumulateSnapshots(
        TransformationStream::from($responses)->using(Transformation::define(
            new AccumulatePartialResponses(
                mode: OutputMode::Tools,
                deserializer: accumulatingDeserializer(),
                transformer: accumulatingTransformer(),
                responseModel: makeAnyResponseModel(AccumulatedUser::class),
            ),
        )),
    );

    expect($result[0]->hasValue())->toBeTrue();
    expect($result[0]->value()->name)->toBe('');
    expect($result[0]->value()->age)->toBe(0);
    expect($result[1]->hasValue())->toBeTrue();
    expect($result[1]->value())->toBeInstanceOf(AccumulatedUser::class);
    expect($result[1]->value()->age)->toBe(30);
});

function accumulatingDeserializer(): CanDeserializeResponse {
    return new class implements CanDeserializeResponse {
        public function deserialize(array $data, ResponseModel $responseModel): Result {
            return Result::success($data);
        }
    };
}

function accumulatingTransformer(): CanTransformResponse {
    return new class implements CanTransformResponse {
        public function transform(mixed $data, ResponseModel $responseModel): Result {
            return Result::success(new AccumulatedUser(
                name: (string) ($data['name'] ?? ''),
                age: (int) ($data['age'] ?? 0),
            ));
        }
    };
}

/**
 * @param iterable<StructuredOutputStreamState> $states
 * @return list<\Cognesy\Instructor\Data\StructuredOutputResponse>
 */
function accumulateSnapshots(iterable $states): array {
    $snapshots = [];

    foreach ($states as $state) {
        $snapshots[] = $state->partialResponse();
    }

    return $snapshots;
}
