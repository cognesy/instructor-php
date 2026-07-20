<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Data\ResponseFailure;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputAttempt;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeClass;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\Enums\ResponseFailureStage;
use Cognesy\Instructor\Events\Attempt\ResponseRetryScheduled;
use Cognesy\Instructor\Events\Response\ResponseDeserializationAttempt;
use Cognesy\Instructor\Events\Response\ResponseDeserializationFailed;
use Cognesy\Instructor\Events\Response\ResponseDeserialized;
use Cognesy\Instructor\Events\Response\ResponseMaterializationFailed;
use Cognesy\Instructor\Events\Response\ResponseMaterialized;
use Cognesy\Instructor\Events\Streaming\PartialResponseGenerationFailed;
use Cognesy\Instructor\Exceptions\StructuredOutputRecoveryException;
use Cognesy\Instructor\Extraction\Contracts\CanExtractResponse;
use Cognesy\Instructor\Extraction\Data\ExtractionInput;
use Cognesy\Instructor\RetryPolicy\DefaultRetryPolicy;
use Cognesy\Instructor\Streaming\Pipeline\AccumulatePartialResponsesReducer;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Instructor\Transformation\Contracts\CanTransformData;
use Cognesy\Instructor\Validation\Contracts\CanValidateObject;
use Cognesy\Instructor\Validation\ValidationResult;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Utils\Result\Result;

final class FailureTelemetryDto
{
    public string $name;
}

final class ThrowingStageExtractor implements CanExtractResponse
{
    public function __construct(private readonly RuntimeException $error) {}

    public function extract(ExtractionInput $input): array
    {
        throw $this->error;
    }

    public function name(): string
    {
        return 'throwing_stage_extractor';
    }
}

final class ThrowingStageDeserializer implements CanDeserializeClass
{
    public function __construct(private readonly RuntimeException $error) {}

    public function fromArray(array $data, string $dataType): mixed
    {
        throw $this->error;
    }
}

final class ThrowingStageValidator implements CanValidateObject
{
    public function __construct(private readonly RuntimeException $error) {}

    public function validate(object $dataObject): ValidationResult
    {
        throw $this->error;
    }
}

final class ThrowingStageTransformer implements CanTransformData
{
    public function __construct(private readonly RuntimeException $error) {}

    public function transform(mixed $data): mixed
    {
        throw $this->error;
    }
}

final class FailureTelemetrySelfTarget implements CanDeserializeSelf
{
    public function __construct(private readonly bool $shouldFail = false) {}

    public function fromArray(array $data): static
    {
        if ($this->shouldFail) {
            throw new RuntimeException('self target failed');
        }

        return clone $this;
    }
}

function stageFailureRuntime(
    ResponseFailureStage $stage,
    EventDispatcher $events,
    RuntimeException $cause,
): \Cognesy\Instructor\StructuredOutputRuntime {
    $content = match ($stage) {
        ResponseFailureStage::SchemaValidation => '{"name":123}',
        default => '{"name":"PRIVATE-CONTENT"}',
    };
    $driver = new FakeInferenceDriver([
        new InferenceResponse(content: $content),
        new InferenceResponse(content: $content),
    ]);

    return match ($stage) {
        ResponseFailureStage::Extraction => makeStructuredRuntime(
            driver: $driver,
            events: $events,
            outputMode: OutputMode::Json,
            maxRetries: 1,
            extractor: new ThrowingStageExtractor($cause),
        ),
        ResponseFailureStage::Deserialization => makeStructuredRuntime(
            driver: $driver,
            events: $events,
            outputMode: OutputMode::Json,
            maxRetries: 1,
            deserializer: new ThrowingStageDeserializer($cause),
        ),
        ResponseFailureStage::ObjectValidation => makeStructuredRuntime(
            driver: $driver,
            events: $events,
            outputMode: OutputMode::Json,
            maxRetries: 1,
            validator: new ThrowingStageValidator($cause),
        ),
        ResponseFailureStage::Transformation => makeStructuredRuntime(
            driver: $driver,
            events: $events,
            outputMode: OutputMode::Json,
            maxRetries: 1,
            transformer: new ThrowingStageTransformer($cause),
        ),
        ResponseFailureStage::SchemaValidation => makeStructuredRuntime(
            driver: $driver,
            events: $events,
            outputMode: OutputMode::Json,
            maxRetries: 1,
        ),
    };
}

dataset('response failure stages', array_map(
    static fn(ResponseFailureStage $stage): array => [$stage],
    ResponseFailureStage::cases(),
));

it('preserves each failure stage through attempts and retry telemetry', function (ResponseFailureStage $stage) {
    $events = new EventDispatcher();
    $retryEvents = [];
    $failureEvents = [];
    $events->addListener(ResponseRetryScheduled::class, function (object $event) use (&$retryEvents): void {
        $retryEvents[] = $event->data;
    });
    $events->addListener(ResponseMaterializationFailed::class, function (object $event) use (&$failureEvents): void {
        $failureEvents[] = $event->data;
    });
    $cause = new RuntimeException("{$stage->value} cause");
    $runtime = stageFailureRuntime($stage, $events, $cause);
    $request = (new StructuredOutput($runtime))
        ->with(messages: 'Extract a name.', responseModel: FailureTelemetryDto::class);

    try {
        $request->get();
        $this->fail('Every configured stage should fail.');
    } catch (StructuredOutputRecoveryException $error) {
        expect($error->errors)->toHaveCount(2);
        $failure = $error->errors[0];
        expect($failure)->toBeInstanceOf(ResponseFailure::class)
            ->and($failure->stage)->toBe($stage);

        if ($stage === ResponseFailureStage::SchemaValidation) {
            expect($failure->cause)->toBeNull();
        } elseif ($stage === ResponseFailureStage::Deserialization) {
            expect($failure->cause?->getPrevious())->toBe($cause);
        } else {
            expect($failure->cause)->toBe($cause);
        }
    }

    expect($retryEvents)->toHaveCount(1)
        ->and($retryEvents[0]['stage'])->toBe($stage->value)
        ->and($retryEvents[0]['phase'])->toBe('response.retry_scheduled')
        ->and($failureEvents)->toHaveCount(2)
        ->and($failureEvents[0]['stage'])->toBe($stage->value)
        ->and($failureEvents[0])->toHaveKeys(['requestId', 'executionId', 'attemptId', 'phaseId', 'errorType']);

    $eventJson = json_encode([$retryEvents, $failureEvents]);
    expect($eventJson)->not->toContain('PRIVATE-CONTENT')
        ->and($eventJson)->not->toContain('reasoningContent')
        ->and($eventJson)->not->toContain('toolArgs');
})->with('response failure stages')->group('structured-output-contract-regression');

it('records the exact failure object and throwable in the retry attempt', function () {
    $events = new EventDispatcher();
    $cause = new RuntimeException('same cause');
    $failure = ResponseFailure::fromError(ResponseFailureStage::Transformation, $cause);
    $result = Result::failure($failure);
    $execution = (new StructuredOutputExecution())
        ->with(config: (new \Cognesy\Instructor\Config\StructuredOutputConfig())->withMaxRetries(1))
        ->withStartedAttempt();

    $updated = (new DefaultRetryPolicy($events))->recordFailure(
        execution: $execution,
        result: $result,
        inference: new InferenceResponse(content: 'private'),
    );

    expect($updated->errors()[0])->toBe($failure)
        ->and($updated->errors()[0]->cause)->toBe($cause);

    $restored = StructuredOutputAttempt::fromArray($updated->attempts()->last()->toArray());
    expect($restored->errors()[0])->toBeInstanceOf(ResponseFailure::class)
        ->and($restored->errors()[0]->stage)->toBe(ResponseFailureStage::Transformation)
        ->and($restored->errors()[0]->cause)->toBeNull();
})->group('structured-output-contract-regression');

it('emits one correlated result-neutral event with the actual result type', function (
    mixed $responseModel,
    string $content,
    string $expectedType,
) {
    $events = new EventDispatcher();
    $captured = [];
    $events->addListener(ResponseMaterialized::class, function (object $event) use (&$captured): void {
        $captured[] = $event->data;
    });
    $runtime = makeStructuredRuntime(
        driver: new FakeInferenceDriver([new InferenceResponse(content: $content)]),
        events: $events,
        outputMode: OutputMode::Json,
    );

    (new StructuredOutput($runtime))
        ->with(messages: 'Extract the value.', responseModel: $responseModel)
        ->get();

    expect($captured)->toHaveCount(1)
        ->and($captured[0]['resultType'])->toBe($expectedType)
        ->and($captured[0]['phase'])->toBe('response.materialization')
        ->and($captured[0])->toHaveKeys(['requestId', 'executionId', 'attemptId', 'phaseId'])
        ->and(json_encode($captured[0]))->not->toContain($content);
})->with([
    'array' => [[
        'type' => 'object',
        'properties' => ['name' => ['type' => 'string']],
        'required' => ['name'],
    ], '{"name":"Ava"}', 'array'],
    'object' => [FailureTelemetryDto::class, '{"name":"Ava"}', FailureTelemetryDto::class],
    'scalar' => [\Cognesy\Instructor\Extras\Scalar\Scalar::integer('value'), '{"value":7}', 'int'],
])->group('structured-output-contract-regression');

it('balances deserialization stage events for every successful target kind', function (mixed $target) {
    $events = new EventDispatcher();
    $counts = ['attempt' => 0, 'success' => 0, 'failure' => 0];
    $events->addListener(ResponseDeserializationAttempt::class, function () use (&$counts): void { $counts['attempt']++; });
    $events->addListener(ResponseDeserialized::class, function () use (&$counts): void { $counts['success']++; });
    $events->addListener(ResponseDeserializationFailed::class, function () use (&$counts): void { $counts['failure']++; });
    $deserializer = new ResponseDeserializer(
        events: $events,
        deserializer: new SymfonyDeserializer(),
        config: new \Cognesy\Instructor\Config\StructuredOutputConfig(),
    );

    $result = $deserializer->deserialize(['name' => 'Ava'], makeAnyResponseModel($target));

    expect($result->isSuccess())->toBeTrue()
        ->and($counts)->toBe(['attempt' => 1, 'success' => 1, 'failure' => 0]);
})->with([
    'array' => [[
        'type' => 'object',
        'properties' => ['name' => ['type' => 'string']],
    ]],
    'stdClass' => [stdClass::class],
    'class' => [FailureTelemetryDto::class],
    'self' => [new FailureTelemetrySelfTarget()],
])->group('structured-output-contract-regression');

it('balances deserialization stage events for self-target failure', function () {
    $events = new EventDispatcher();
    $counts = ['attempt' => 0, 'success' => 0, 'failure' => 0];
    $events->addListener(ResponseDeserializationAttempt::class, function () use (&$counts): void { $counts['attempt']++; });
    $events->addListener(ResponseDeserialized::class, function () use (&$counts): void { $counts['success']++; });
    $events->addListener(ResponseDeserializationFailed::class, function () use (&$counts): void { $counts['failure']++; });
    $deserializer = new ResponseDeserializer(
        events: $events,
        deserializer: new SymfonyDeserializer(),
        config: new \Cognesy\Instructor\Config\StructuredOutputConfig(),
    );

    $result = $deserializer->deserialize(
        ['name' => 'Ava'],
        makeAnyResponseModel(new FailureTelemetrySelfTarget(shouldFail: true)),
    );

    expect($result->isFailure())->toBeTrue()
        ->and($counts)->toBe(['attempt' => 1, 'success' => 0, 'failure' => 1]);
})->group('structured-output-contract-regression');

it('deduplicates streaming preview failures and excludes raw content', function () {
    $events = new EventDispatcher();
    $captured = [];
    $events->addListener(PartialResponseGenerationFailed::class, function (object $event) use (&$captured): void {
        $captured[] = $event->data;
    });
    $inner = new class implements Reducer {
        public function init(): mixed { return null; }
        public function step(mixed $accumulator, mixed $reducible): mixed { return $accumulator; }
        public function complete(mixed $accumulator): mixed { return $accumulator; }
    };
    $materializer = makeTestMaterializer(
        deserializer: new class implements CanDeserializeResponse {
            public function deserialize(array $data, ResponseModel $responseModel): Result
            {
                return Result::failure(new RuntimeException('PRIVATE-PARTIAL-CONTENT'));
            }
        },
        transformer: new class implements \Cognesy\Instructor\Transformation\Contracts\CanTransformResponse {
            public function transform(mixed $data, ResponseModel $responseModel): Result
            {
                return Result::success($data);
            }
        },
    );
    $reducer = new AccumulatePartialResponsesReducer(
        inner: $inner,
        mode: OutputMode::Tools,
        materializer: $materializer,
        responseModel: makeAnyResponseModel(FailureTelemetryDto::class),
        events: $events,
    );
    $accumulator = $reducer->init();
    foreach (range(1, 8) as $index) {
        $accumulator = $reducer->step($accumulator, new PartialInferenceDelta(
            toolId: "tool-{$index}",
            toolName: 'extract_data',
            toolArgs: '{"name":"PRIVATE-PARTIAL-CONTENT"}',
        ));
    }

    expect($captured)->toHaveCount(1)
        ->and($captured[0]['stage'])->toBe(ResponseFailureStage::Deserialization->value)
        ->and(json_encode($captured))->not->toContain('PRIVATE-PARTIAL-CONTENT');
})->group('structured-output-contract-regression');
