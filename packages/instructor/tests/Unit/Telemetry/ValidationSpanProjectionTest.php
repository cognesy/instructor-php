<?php declare(strict_types=1);

use Cognesy\Instructor\Events\Response\CustomResponseValidationAttempt;
use Cognesy\Instructor\Events\Response\ResponseValidated;
use Cognesy\Instructor\Events\Response\ResponseValidationAttempt;
use Cognesy\Instructor\Events\Response\ResponseValidationFailed;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputStarted;
use Cognesy\Instructor\Telemetry\InstructorTelemetryProjector;
use Cognesy\Instructor\Telemetry\PhaseTelemetryContext;
use Cognesy\Telemetry\Adapters\OTel\OtelExporter;
use Cognesy\Telemetry\Application\Registry\TraceRegistry;
use Cognesy\Telemetry\Application\Telemetry;
use Cognesy\Telemetry\Domain\Envelope\OperationCorrelation;
use Cognesy\Telemetry\Domain\Envelope\OperationDescriptor;
use Cognesy\Telemetry\Domain\Envelope\OperationKind;
use Cognesy\Telemetry\Domain\Envelope\TelemetryEnvelope;
use Cognesy\Telemetry\Domain\Observation\ObservationStatus;

/**
 * Validation used to reach telemetry only as an error log, so a successful validation left no
 * trace at all and a failing one was indistinguishable from any other logged error. The span is
 * built out of the attempt/result pair ResponseValidator already emitted - no second lifecycle.
 */

const VALIDATE_ROOT = 'so-exec-v1';
const VALIDATE_PHASE = 'so-exec-v1:response.validation:attempt-1';

function validationHarness(): array
{
    $otel = new OtelExporter();
    $telemetry = new Telemetry(new TraceRegistry(), $otel);
    $projector = new InstructorTelemetryProjector($telemetry);

    $projector->project(new StructuredOutputStarted([
        'executionId' => VALIDATE_ROOT,
        'requestId' => 'so-req-v1',
        'model' => 'gpt-test',
    ]));

    return [$projector, $telemetry, $otel];
}

function validationContext(): PhaseTelemetryContext
{
    return new PhaseTelemetryContext(
        envelope: new TelemetryEnvelope(
            operation: new OperationDescriptor(
                id: VALIDATE_PHASE,
                type: 'structured_output.validation',
                name: 'structured_output.validate',
                kind: OperationKind::Span,
            ),
            correlation: OperationCorrelation::child(
                rootOperationId: VALIDATE_ROOT,
                parentOperationId: VALIDATE_ROOT,
                requestId: 'so-req-v1',
            ),
        ),
        executionId: VALIDATE_ROOT,
        phaseId: VALIDATE_PHASE,
        phase: 'response.validation',
        attemptId: 'attempt-1',
    );
}

function validationObservation(OtelExporter $otel): ?object
{
    foreach ($otel->observations() as $observation) {
        if ($observation->name() === 'structured_output.validate') {
            return $observation;
        }
    }
    return null;
}

it('opens the validation span on the attempt and closes it as ok when validation passes', function () {
    [$projector, $telemetry, $otel] = validationHarness();
    $context = validationContext();

    $projector->project(new ResponseValidationAttempt([
        ...$context->eventData(),
        'responseClass' => stdClass::class,
        'fieldCount' => 2,
        'validator' => 'SymfonyValidator',
    ]));

    // Open before the result arrives - the attempt event is the span's start, not a log line.
    expect($telemetry->spanReference(VALIDATE_PHASE))->not->toBeNull();

    $projector->project(new ResponseValidated([
        ...$context->eventData(),
        'validation' => ['isValid' => true, 'message' => '', 'errors' => []],
    ]));

    $observation = validationObservation($otel);
    expect($observation)->not->toBeNull()
        ->and($observation->status())->toBe(ObservationStatus::Ok);
});

it('nests the validation span under the structured output root', function () {
    [$projector, $telemetry] = validationHarness();

    $root = $telemetry->spanReference(VALIDATE_ROOT);
    $projector->project(new ResponseValidationAttempt([...validationContext()->eventData()]));
    $child = $telemetry->spanReference(VALIDATE_PHASE);

    expect($child?->traceId()->value())->toBe($root?->traceId()->value())
        ->and($child?->parentSpanId()?->value())->toBe($root?->spanId()->value())
        ->and($child?->spanId()->value())->not->toBe($root?->spanId()->value());
});

it('opens the same span for self-validating responses', function () {
    // CanValidateSelf takes a different code path in ResponseValidator but is the same phase.
    [$projector, $telemetry] = validationHarness();

    $projector->project(new CustomResponseValidationAttempt([
        ...validationContext()->eventData(),
        'validator' => 'self',
    ]));

    expect($telemetry->spanReference(VALIDATE_PHASE))->not->toBeNull();
});

it('fails the validation span and counts the errors without copying their values', function () {
    [$projector, $_telemetry, $otel] = validationHarness();
    $context = validationContext();

    $projector->project(new ResponseValidationAttempt([...$context->eventData()]));
    $projector->project(new ResponseValidationFailed([
        ...$context->eventData(),
        'errorMessage' => 'Data validation failed',
        'validation' => [
            'isValid' => false,
            'message' => 'Data validation failed',
            'errors' => [
                ['field' => 'age', 'value' => -1, 'message' => 'must be positive'],
                ['field' => 'name', 'value' => '', 'message' => 'must not be blank'],
            ],
        ],
    ]));

    $observation = validationObservation($otel);
    $attributes = $observation?->attributes()->toArray() ?? [];

    expect($observation?->status())->toBe(ObservationStatus::Error)
        ->and($attributes['error.message'] ?? null)->toBe('Data validation failed')
        ->and($attributes['structured_output.validation.error_count'] ?? null)->toBe(2)
        // The offending values stay in the event, out of the span.
        ->and(json_encode($attributes))->not->toContain('must be positive');
});

it('still logs an unstamped validation failure instead of dropping it', function () {
    // An emitter that carries correlation ids but no envelope keeps exactly the error log it
    // had before this change - the span is an addition for stamped emitters, not a swap.
    [$projector, $_telemetry, $otel] = validationHarness();

    $projector->project(new ResponseValidationFailed([
        'executionId' => VALIDATE_ROOT,
        'errorMessage' => 'Response object validation failed.',
        'errorType' => RuntimeException::class,
    ]));

    expect(validationObservation($otel))->toBeNull();

    $logged = null;
    foreach ($otel->observations() as $observation) {
        if ($observation->name() === 'structured_output.response_validation_failed') {
            $logged = $observation;
        }
    }
    expect($logged)->not->toBeNull()
        ->and($logged->status())->toBe(ObservationStatus::Error);
});

it('does not open a stray root for an unstamped validation attempt', function () {
    [$projector, $_telemetry, $otel] = validationHarness();
    $before = count($otel->observations());

    $projector->project(new ResponseValidationAttempt(['responseClass' => stdClass::class, 'fieldCount' => 1]));
    $projector->project(new ResponseValidated(['validation' => ['isValid' => true, 'errors' => []]]));

    expect(count($otel->observations()))->toBe($before);
});
