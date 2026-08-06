<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Contracts\CanDetermineRetry;
use Cognesy\Instructor\Core\AttemptProcessor;
use Cognesy\Instructor\Core\ResponseGenerator;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Data\StructuredOutputAttempt;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Enums\ExecutionStatus;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\Events\Response\ResponseValidated;
use Cognesy\Instructor\Events\Response\ResponseValidationAttempt;
use Cognesy\Instructor\Events\Response\ResponseValidationFailed;
use Cognesy\Instructor\Extraction\ResponseExtractor;
use Cognesy\Instructor\Telemetry\InstructorTelemetryProjector;
use Cognesy\Instructor\Telemetry\StructuredOutputTelemetry;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Instructor\Validation\Contracts\CanValidateObject;
use Cognesy\Instructor\Validation\ResponseValidator;
use Cognesy\Instructor\Validation\ValidationError;
use Cognesy\Instructor\Validation\ValidationResult;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Telemetry\Adapters\OTel\OtelExporter;
use Cognesy\Telemetry\Application\Registry\TraceRegistry;
use Cognesy\Telemetry\Application\Telemetry;
use Cognesy\Telemetry\Domain\Envelope\TelemetryEnvelope;
use Cognesy\Telemetry\Domain\Observation\ObservationStatus;
use Cognesy\Utils\Result\Result;

/**
 * The runtime half of the validation-span work.
 *
 * ValidationSpanProjectionTest proves the projector builds the span; this proves the live path
 * actually stamps the events, all the way down from AttemptProcessor through ResponseGenerator
 * and ResponseMaterializer into ResponseValidator. Before this the validation events carried no
 * ids whatsoever and validation was visible only as an error log.
 */

/** Real AttemptProcessor over a real ResponseValidator; returns [$captured, $execution]. */
function captureValidationEvents(CanValidateObject $objectValidator): array
{
    $captured = [];
    $events = new EventDispatcher('test');
    $events->wiretap(function (object $event) use (&$captured): void {
        $captured[] = $event;
    });

    $execution = new StructuredOutputExecution(
        request: new StructuredOutputRequest(
            messages: Messages::fromString('Extract user'),
            requestedSchema: ['type' => 'object'],
        ),
        config: new StructuredOutputConfig(outputMode: OutputMode::Json),
        responseModel: makeAnyResponseModel(stdClass::class),
        activeAttempt: new StructuredOutputAttempt(isFinalized: false),
        status: ExecutionStatus::Running,
    );

    $config = new StructuredOutputConfig(outputMode: OutputMode::Json);
    $processor = new AttemptProcessor(
        responseGenerator: new ResponseGenerator(
            materializer: makeTestMaterializer(
                new class implements CanDeserializeResponse {
                    public function deserialize(array $data, ResponseModel $responseModel): Result {
                        return Result::success((object) $data);
                    }
                },
                new class implements CanTransformResponse {
                    public function transform(mixed $data, ResponseModel $responseModel): Result {
                        return Result::success($data);
                    }
                },
                new ResponseValidator($events, $objectValidator, $config),
            ),
            extractor: new ResponseExtractor(events: $events),
        ),
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
        events: $events,
    );

    try {
        $processor->processInferenceResponse($execution, new InferenceResponse(content: '{"name":"Ann"}'));
    } catch (Throwable) {
        // A rejected response is one of the cases under test; the events are what matter.
    }

    return [$captured, $execution];
}

function passingObjectValidator(): CanValidateObject
{
    return new class implements CanValidateObject {
        public function validate(object $dataObject): ValidationResult {
            return ValidationResult::valid();
        }
    };
}

function rejectingObjectValidator(): CanValidateObject
{
    return new class implements CanValidateObject {
        public function validate(object $dataObject): ValidationResult {
            return ValidationResult::invalid(
                [new ValidationError('name', 'Ann', 'is not allowed')],
                'Data validation failed',
            );
        }
    };
}

function firstOfType(array $events, string $class): ?object
{
    foreach ($events as $event) {
        if ($event instanceof $class) {
            return $event;
        }
    }
    return null;
}

it('stamps the validation attempt as a child of the execution root', function () {
    [$captured, $execution] = captureValidationEvents(passingObjectValidator());

    $attempt = firstOfType($captured, ResponseValidationAttempt::class);
    expect($attempt)->not->toBeNull();

    $envelope = TelemetryEnvelope::fromArray($attempt->data[TelemetryEnvelope::KEY]);
    $executionId = $execution->id()->toString();

    expect($envelope->operation()->name())->toBe('structured_output.validate')
        ->and($envelope->operation()->id())
        ->toBe(StructuredOutputTelemetry::phaseId($execution, 'response.validation'))
        ->and($envelope->correlation()->rootOperationId())->toBe($executionId)
        ->and($envelope->correlation()->parentOperationId())->toBe($executionId);
});

it('closes the validation span under the key the attempt opened', function () {
    [$captured] = captureValidationEvents(passingObjectValidator());

    $attempt = firstOfType($captured, ResponseValidationAttempt::class);
    $validated = firstOfType($captured, ResponseValidated::class);

    expect($validated)->not->toBeNull()
        ->and($validated->data[TelemetryEnvelope::KEY]['operation']['id'])
        ->toBe($attempt->data[TelemetryEnvelope::KEY]['operation']['id']);
});

it('uses a validation phase id distinct from the extraction one', function () {
    // Both are children of the same execution; keying them alike would collapse them into one.
    [$_captured, $execution] = captureValidationEvents(passingObjectValidator());

    expect(StructuredOutputTelemetry::phaseId($execution, 'response.validation'))
        ->not->toBe(StructuredOutputTelemetry::phaseId($execution, 'response.extraction'));
});

it('stamps a rejected response and keeps the error message readable', function () {
    [$captured] = captureValidationEvents(rejectingObjectValidator());

    $failed = firstOfType($captured, ResponseValidationFailed::class);

    expect($failed)->not->toBeNull()
        ->and($failed->data)->toHaveKey(TelemetryEnvelope::KEY)
        ->and($failed->data['errorMessage'])->toBeString()
        ->and($failed->data['validation']['errors'])->toHaveCount(1);
});

it('projects the live event stream into a validation span nested under the execution', function () {
    $otel = new OtelExporter();
    $telemetry = new Telemetry(new TraceRegistry(), $otel);
    $projector = new InstructorTelemetryProjector($telemetry);

    [$captured, $execution] = captureValidationEvents(passingObjectValidator());

    $telemetry->openRoot($execution->id()->toString(), 'structured_output.execute');
    foreach ($captured as $event) {
        $projector->project($event);
    }

    $root = $telemetry->spanReference($execution->id()->toString());
    $exported = null;
    foreach ($otel->observations() as $observation) {
        if ($observation->name() === 'structured_output.validate') {
            $exported = $observation;
        }
    }

    expect($exported)->not->toBeNull()
        ->and($exported->status())->toBe(ObservationStatus::Ok)
        ->and($exported->spanReference()->traceId()->value())->toBe($root->traceId()->value())
        ->and($exported->spanReference()->parentSpanId()?->value())->toBe($root->spanId()->value());
});

it('marks the projected validation span as an error when the response is rejected', function () {
    $otel = new OtelExporter();
    $telemetry = new Telemetry(new TraceRegistry(), $otel);
    $projector = new InstructorTelemetryProjector($telemetry);

    [$captured, $execution] = captureValidationEvents(rejectingObjectValidator());

    $telemetry->openRoot($execution->id()->toString(), 'structured_output.execute');
    foreach ($captured as $event) {
        $projector->project($event);
    }

    $exported = null;
    foreach ($otel->observations() as $observation) {
        if ($observation->name() === 'structured_output.validate') {
            $exported = $observation;
        }
    }

    expect($exported?->status())->toBe(ObservationStatus::Error)
        ->and($exported?->attributes()->toArray()['structured_output.validation.error_count'] ?? null)->toBe(1);
});
