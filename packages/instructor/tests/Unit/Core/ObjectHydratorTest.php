<?php declare(strict_types=1);

use Cognesy\Instructor\Core\ObjectHydrator;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Instructor\Validation\Contracts\CanValidateResponse;
use Cognesy\Utils\Result\Result;

final class HydratedUser
{
    public function __construct(public string $name = '', public int $age = 0) {}
}

function hydratorSpy(array &$calls): ObjectHydrator {
    return new ObjectHydrator(
        deserializer: new class($calls) implements CanDeserializeResponse {
            public function __construct(private array &$calls) {}
            public function deserialize(array $data, ResponseModel $responseModel): Result {
                $this->calls[] = 'deserialize';
                return Result::success(new HydratedUser(
                    name: (string) ($data['name'] ?? ''),
                    age: (int) ($data['age'] ?? 0),
                ));
            }
        },
        validator: new class($calls) implements CanValidateResponse {
            public function __construct(private array &$calls) {}
            public function validate(object $response, ResponseModel $responseModel): Result {
                $this->calls[] = 'validate';
                return $response->age >= 0
                    ? Result::success($response)
                    : Result::failure('age must be non-negative');
            }
        },
        transformer: new class($calls) implements CanTransformResponse {
            public function __construct(private array &$calls) {}
            public function transform(mixed $data, ResponseModel $responseModel): Result {
                $this->calls[] = 'transform';
                return Result::success($data);
            }
        },
    );
}

it('hydrate runs deserialize -> validate -> transform in order', function () {
    $calls = [];
    $result = hydratorSpy($calls)->hydrate(
        ['name' => 'Ann', 'age' => 30],
        makeAnyResponseModel(HydratedUser::class),
    );

    expect($result->isSuccess())->toBeTrue();
    expect($result->unwrap())->toBeInstanceOf(HydratedUser::class);
    expect($calls)->toBe(['deserialize', 'validate', 'transform']);
});

it('hydrate stops at validation failure without transforming', function () {
    $calls = [];
    $result = hydratorSpy($calls)->hydrate(
        ['name' => 'Ann', 'age' => -1],
        makeAnyResponseModel(HydratedUser::class),
    );

    expect($result->isFailure())->toBeTrue();
    expect($calls)->toBe(['deserialize', 'validate']);
});

it('hydratePartial skips validation entirely', function () {
    $calls = [];
    $result = hydratorSpy($calls)->hydratePartial(
        ['name' => 'An', 'age' => -1], // would fail validation
        makeAnyResponseModel(HydratedUser::class),
    );

    expect($result->isSuccess())->toBeTrue();
    expect($calls)->toBe(['deserialize', 'transform']);
});

it('finalize validates and transforms an already-built object', function () {
    $calls = [];
    $result = hydratorSpy($calls)->finalize(
        new HydratedUser(name: 'Ann', age: 30),
        makeAnyResponseModel(HydratedUser::class),
    );

    expect($result->isSuccess())->toBeTrue();
    expect($calls)->toBe(['validate', 'transform']);
});

it('finalize passes non-object values through untouched', function () {
    $calls = [];
    $result = hydratorSpy($calls)->finalize(
        ['already' => 'array'],
        makeAnyResponseModel(HydratedUser::class),
    );

    expect($result->isSuccess())->toBeTrue();
    expect($result->unwrap())->toBe(['already' => 'array']);
    expect($calls)->toBe([]);
});

it('carries stage exceptions as failure results holding the throwable', function () {
    $hydrator = new ObjectHydrator(
        deserializer: new class implements CanDeserializeResponse {
            public function deserialize(array $data, ResponseModel $responseModel): Result {
                throw new RuntimeException('boom');
            }
        },
        validator: new class implements CanValidateResponse {
            public function validate(object $response, ResponseModel $responseModel): Result {
                return Result::success($response);
            }
        },
        transformer: new class implements CanTransformResponse {
            public function transform(mixed $data, ResponseModel $responseModel): Result {
                return Result::success($data);
            }
        },
    );

    $result = $hydrator->hydrate(['x' => 1], makeAnyResponseModel(HydratedUser::class));

    expect($result->isFailure())->toBeTrue();
    expect($result->error())->toBeInstanceOf(RuntimeException::class);
    expect($result->errorMessage())->toContain('boom');
});
