<?php declare(strict_types=1);

use Cognesy\Instructor\Core\ResponseMaterializer;
use Cognesy\Instructor\Data\ResponseFailure;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Enums\ResponseFailureStage;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Instructor\Validation\Contracts\CanValidateResponse;
use Cognesy\Utils\Result\Result;

final class MaterializedUser
{
    public function __construct(public string $name = '', public int $age = 0) {}
}

function materializerSpy(array &$calls): ResponseMaterializer
{
    return new ResponseMaterializer(
        deserializer: new class($calls) implements CanDeserializeResponse {
            public function __construct(private array &$calls) {}

            public function deserialize(array $data, ResponseModel $responseModel): Result
            {
                $this->calls[] = 'deserialize';
                if ($responseModel->outputFormat()->isArray()) {
                    return Result::success($data);
                }

                return Result::success(new MaterializedUser(
                    name: (string) ($data['name'] ?? ''),
                    age: (int) ($data['age'] ?? 0),
                ));
            }
        },
        validator: new class($calls) implements CanValidateResponse {
            public function __construct(private array &$calls) {}

            public function validate(object $response, ResponseModel $responseModel): Result
            {
                $this->calls[] = 'validate';
                return $response->age >= 0
                    ? Result::success($response)
                    : Result::failure('age must be non-negative');
            }
        },
        transformer: new class($calls) implements CanTransformResponse {
            public function __construct(private array &$calls) {}

            public function transform(mixed $data, ResponseModel $responseModel): Result
            {
                $this->calls[] = 'transform';
                return Result::success($data);
            }
        },
    );
}

function materializerArraySchema(): array
{
    return [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'age' => ['type' => 'integer'],
        ],
        'required' => ['name', 'age'],
    ];
}

it('materializes through deserialize, object validation, and transformation in order', function () {
    $calls = [];
    $result = materializerSpy($calls)->materialize(
        ['name' => 'Ann', 'age' => 30],
        makeAnyResponseModel(MaterializedUser::class),
    );

    expect($result->unwrap())->toBeInstanceOf(MaterializedUser::class)
        ->and($calls)->toBe(['deserialize', 'validate', 'transform']);
});

it('stops on schema-data validation before deserialization', function () {
    $calls = [];
    $result = materializerSpy($calls)->materialize(
        ['name' => 'Ann', 'age' => 'thirty'],
        makeAnyResponseModel(MaterializedUser::class),
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->errorMessage())->toContain('age')
        ->and($calls)->toBe([]);
});

it('stops at object validation failure without transforming', function () {
    $calls = [];
    $result = materializerSpy($calls)->materialize(
        ['name' => 'Ann', 'age' => -1],
        makeAnyResponseModel(MaterializedUser::class),
    );

    expect($result->isFailure())->toBeTrue()
        ->and($calls)->toBe(['deserialize', 'validate']);
});

it('previews through deserialization without final validation or transformation', function () {
    $calls = [];
    $result = materializerSpy($calls)->preview(
        ['name' => 'An', 'age' => -1],
        makeAnyResponseModel(MaterializedUser::class),
    );

    expect($result->unwrap())->toBeInstanceOf(MaterializedUser::class)
        ->and($calls)->toBe(['deserialize']);
});

it('transforms array targets after schema validation', function () {
    $calls = [];
    $result = materializerSpy($calls)->materialize(
        ['name' => 'Ann', 'age' => 30],
        makeAnyResponseModel(materializerArraySchema()),
    );

    expect($result->unwrap())->toBe(['name' => 'Ann', 'age' => 30])
        ->and($calls)->toBe(['deserialize', 'transform']);
});

it('validates and transforms matching pre-valued objects without deserializing again', function () {
    $calls = [];
    $value = new MaterializedUser(name: 'Ann', age: 30);
    $result = materializerSpy($calls)->materialize(
        $value,
        makeAnyResponseModel(MaterializedUser::class),
    );

    expect($result->unwrap())->toBe($value)
        ->and($calls)->toBe(['validate', 'transform']);
});

it('rejects pre-valued inputs that do not match the output target', function () {
    $calls = [];
    $result = materializerSpy($calls)->materialize(
        new stdClass(),
        makeAnyResponseModel(MaterializedUser::class),
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->errorMessage())->toContain('does not match output target')
        ->and($calls)->toBe([]);
});

it('carries stage exceptions as failure results holding the throwable', function () {
    $materializer = new ResponseMaterializer(
        deserializer: new class implements CanDeserializeResponse {
            public function deserialize(array $data, ResponseModel $responseModel): Result
            {
                throw new RuntimeException('boom');
            }
        },
        validator: new class implements CanValidateResponse {
            public function validate(object $response, ResponseModel $responseModel): Result
            {
                return Result::success($response);
            }
        },
        transformer: new class implements CanTransformResponse {
            public function transform(mixed $data, ResponseModel $responseModel): Result
            {
                return Result::success($data);
            }
        },
    );

    $result = $materializer->materialize(
        ['name' => 'Ann', 'age' => 30],
        makeAnyResponseModel(MaterializedUser::class),
    );

    $failure = $result->error();
    expect($failure)->toBeInstanceOf(ResponseFailure::class)
        ->and($failure->stage)->toBe(ResponseFailureStage::Deserialization)
        ->and($failure->cause)->toBeInstanceOf(RuntimeException::class)
        ->and($result->errorMessage())->toContain('boom');
});
