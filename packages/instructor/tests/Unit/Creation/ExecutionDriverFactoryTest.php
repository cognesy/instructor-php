<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Core\ResponseMaterializer;
use Cognesy\Instructor\Core\StructuredPromptRequestMaterializer;
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
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\PendingInference;
use Cognesy\Utils\Result\Result;

function makeIdentityProbeFactory(): ExecutionDriverFactory {
    return new ExecutionDriverFactory(
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
            public function validate(object $response, \Cognesy\Instructor\Data\ResponseModel $responseModel, ?\Cognesy\Instructor\Telemetry\PhaseTelemetryContext $telemetry = null): Result {
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
        requestMaterializer: new StructuredPromptRequestMaterializer(),
    );
}

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
            public function validate(object $response, \Cognesy\Instructor\Data\ResponseModel $responseModel, ?\Cognesy\Instructor\Telemetry\PhaseTelemetryContext $telemetry = null): Result {
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
        requestMaterializer: new StructuredPromptRequestMaterializer(),
    );

    $execution = new StructuredOutputExecution(
        request: (new StructuredOutputRequest(messages: Messages::fromString('test')))->withStreamed(),
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
            public function validate(object $response, \Cognesy\Instructor\Data\ResponseModel $responseModel, ?\Cognesy\Instructor\Telemetry\PhaseTelemetryContext $telemetry = null): Result {
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
        requestMaterializer: new StructuredPromptRequestMaterializer(),
    );

    $execution = new StructuredOutputExecution(
        request: new StructuredOutputRequest(messages: Messages::fromString('test')),
        config: new StructuredOutputConfig(),
    );

    expect($factory->makeExecutionDriver($execution))->toBeInstanceOf(SyncExecutionDriver::class);
});

/**
 * The streaming driver materializes stream previews through its own $materializer while the
 * response generator materializes the final value through its own. They used to be two
 * separately-constructed instances built from the same collaborators, so the two paths could
 * drift apart silently. Both are private, so identity is checked by reflection rather than by
 * reintroducing an accessor nothing else needs.
 */
it('shares one ResponseMaterializer between the streaming driver and its response generator', function () {
    $factory = makeIdentityProbeFactory();

    $driver = $factory->makeStreamingExecutionDriver(new StructuredOutputExecution(
        request: (new StructuredOutputRequest(messages: Messages::fromString('test')))->withStreamed(),
        config: new StructuredOutputConfig(),
    ));

    $read = static function (object $target, string $property): mixed {
        $reflected = new ReflectionProperty($target, $property);
        return $reflected->getValue($target);
    };

    $driverMaterializer = $read($driver, 'materializer');
    $generatorMaterializer = $read(
        $read($read($driver, 'attemptProcessor'), 'responseGenerator'),
        'materializer',
    );

    expect($driverMaterializer)->toBeInstanceOf(ResponseMaterializer::class)
        ->and($generatorMaterializer)->toBe($driverMaterializer);
});
