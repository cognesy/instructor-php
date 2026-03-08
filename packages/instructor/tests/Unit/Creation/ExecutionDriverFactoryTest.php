<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Core\StreamingExecutionDriver;
use Cognesy\Instructor\Core\SyncExecutionDriver;
use Cognesy\Instructor\Creation\ExecutionDriverFactory;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Extraction\Contracts\CanExtractResponse;
use Cognesy\Instructor\Extraction\Data\ExtractionInput;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Instructor\Validation\Contracts\CanValidateResponse;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\PendingInference;
use Cognesy\Utils\Result\Result;

it('creates a streaming driver for streamed executions', function () {
    $factory = new ExecutionDriverFactory(
        inference: new class implements CanCreateInference {
            public function create(InferenceRequest $request): PendingInference {
                throw new RuntimeException('not used');
            }
        },
        responseDeserializer: new class implements CanDeserializeResponse {
            public function deserialize(array $data, \Cognesy\Instructor\Data\ResponseModel $responseModel): Result {
                return Result::success($data);
            }
        },
        responseValidator: new class implements CanValidateResponse {
            public function validate(object $response, \Cognesy\Instructor\Data\ResponseModel $responseModel): Result {
                return Result::success($response);
            }
        },
        responseTransformer: new class implements CanTransformResponse {
            public function transform(mixed $data, \Cognesy\Instructor\Data\ResponseModel $responseModel): Result {
                return Result::success($data);
            }
        },
        events: new EventDispatcher(),
        extractor: new class implements CanExtractResponse {
            public function extract(ExtractionInput $input): array {
                return [];
            }

            public function name(): string {
                return 'test-extractor';
            }
        },
    );

    $execution = new StructuredOutputExecution(
        request: (new StructuredOutputRequest(messages: 'test'))->withStreamed(),
        config: new StructuredOutputConfig(),
    );

    expect($factory->makeExecutionDriver($execution))->toBeInstanceOf(StreamingExecutionDriver::class);
});

it('creates a sync driver for non streamed executions', function () {
    $factory = new ExecutionDriverFactory(
        inference: new class implements CanCreateInference {
            public function create(InferenceRequest $request): PendingInference {
                throw new RuntimeException('not used');
            }
        },
        responseDeserializer: new class implements CanDeserializeResponse {
            public function deserialize(array $data, \Cognesy\Instructor\Data\ResponseModel $responseModel): Result {
                return Result::success($data);
            }
        },
        responseValidator: new class implements CanValidateResponse {
            public function validate(object $response, \Cognesy\Instructor\Data\ResponseModel $responseModel): Result {
                return Result::success($response);
            }
        },
        responseTransformer: new class implements CanTransformResponse {
            public function transform(mixed $data, \Cognesy\Instructor\Data\ResponseModel $responseModel): Result {
                return Result::success($data);
            }
        },
        events: new EventDispatcher(),
        extractor: new class implements CanExtractResponse {
            public function extract(ExtractionInput $input): array {
                return [];
            }

            public function name(): string {
                return 'test-extractor';
            }
        },
    );

    $execution = new StructuredOutputExecution(
        request: new StructuredOutputRequest(messages: 'test'),
        config: new StructuredOutputConfig(),
    );

    expect($factory->makeExecutionDriver($execution))->toBeInstanceOf(SyncExecutionDriver::class);
});

