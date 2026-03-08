<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Contracts\CanDetermineRetry;
use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Core\InferenceProvider;
use Cognesy\Instructor\Core\RequestMaterializer;
use Cognesy\Instructor\Core\StreamingExecutionDriver;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Polyglot\Inference\PendingInference;
use Cognesy\Utils\Result\Result;

final class StreamingDriverUser
{
    public function __construct(
        public string $name,
        public int $age,
    ) {}
}

it('emits live partials and one final response from the streaming driver', function () {
    $driver = new StreamingExecutionDriver(
        execution: new StructuredOutputExecution(
            request: (new StructuredOutputRequest(
                messages: 'Extract user',
                requestedSchema: StreamingDriverUser::class,
            ))->withStreamed(),
            config: new StructuredOutputConfig(outputMode: OutputMode::Json),
            responseModel: makeAnyResponseModel(StreamingDriverUser::class),
        ),
        inferenceProvider: new InferenceProvider(
            inference: new class implements CanCreateInference {
                public function create(InferenceRequest $request): PendingInference {
                    $fakeDriver = new FakeInferenceDriver(
                        responses: [],
                        streamBatches: [[
                            new PartialInferenceResponse(contentDelta: '{"name":"Ann"'),
                            new PartialInferenceResponse(contentDelta: ',"age":30}'),
                            new PartialInferenceResponse(finishReason: 'stop'),
                        ]],
                    );

                    return new PendingInference(
                        execution: InferenceExecution::fromRequest($request),
                        driver: $fakeDriver,
                        eventDispatcher: new EventDispatcher(),
                    );
                }
            },
            requestMaterializer: new RequestMaterializer(),
        ),
        deserializer: new class implements CanDeserializeResponse {
            public function deserialize(array $data, ResponseModel $responseModel): Result {
                return Result::success($data);
            }
        },
        transformer: new class implements CanTransformResponse {
            public function transform(mixed $data, ResponseModel $responseModel): Result {
                return Result::success(new StreamingDriverUser(
                    name: (string) ($data['name'] ?? ''),
                    age: (int) ($data['age'] ?? 0),
                ));
            }
        },
        responseGenerator: new class implements CanGenerateResponse {
            public function makeResponse(
                InferenceResponse $response,
                ResponseModel $responseModel,
                OutputMode $mode,
                mixed $prebuiltValue = null,
            ): Result {
                return Result::success(new StreamingDriverUser(name: 'Ann', age: 30));
            }
        },
        retryPolicy: new class implements CanDetermineRetry {
            public function shouldRetry(StructuredOutputExecution $execution, Result $validationResult): bool {
                return false;
            }

            public function recordFailure(
                StructuredOutputExecution $execution,
                Result $validationResult,
                InferenceResponse $inference,
            ): StructuredOutputExecution {
                return $execution;
            }

            public function prepareRetry(StructuredOutputExecution $execution): StructuredOutputExecution {
                return $execution;
            }

            public function finalizeOrThrow(StructuredOutputExecution $execution, Result $validationResult): mixed {
                return null;
            }
        },
        events: new EventDispatcher(),
    );

    $emissions = [];
    while ($driver->hasNextEmission()) {
        $emissions[] = $driver->nextEmission();
    }

    expect($emissions)->toHaveCount(3);
    expect($emissions[0]?->isFinal())->toBeFalse();
    expect($emissions[0])->toBeInstanceOf(\Cognesy\Instructor\Data\StructuredOutputResponse::class);
    expect($emissions[1])->toBeInstanceOf(\Cognesy\Instructor\Data\StructuredOutputResponse::class);
    expect($emissions[1]?->hasValue())->toBeTrue();
    expect($emissions[2]?->isFinal())->toBeTrue();
    expect($emissions[2]?->rawResponse())->toBeInstanceOf(InferenceResponse::class);
    expect($driver->execution()->output())->toBeInstanceOf(StreamingDriverUser::class);
    expect($driver->execution()->isFinalized())->toBeTrue();
});
