<?php declare(strict_types=1);

use Cognesy\Instructor\Events\Extraction\ExtractionCompleted;
use Cognesy\Instructor\Events\Extraction\ExtractionFailed;
use Cognesy\Instructor\Events\Extraction\ExtractionStarted;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputStarted;
use Cognesy\Instructor\Telemetry\PhaseTelemetryContext;
use Cognesy\Instructor\Telemetry\InstructorTelemetryProjector;
use Cognesy\Telemetry\Adapters\OTel\OtelExporter;
use Cognesy\Telemetry\Application\Registry\TraceRegistry;
use Cognesy\Telemetry\Application\Telemetry;
use Cognesy\Telemetry\Domain\Envelope\OperationCorrelation;
use Cognesy\Telemetry\Domain\Envelope\OperationDescriptor;
use Cognesy\Telemetry\Domain\Envelope\OperationKind;
use Cognesy\Telemetry\Domain\Envelope\TelemetryEnvelope;
use Cognesy\Telemetry\Domain\Observation\ObservationStatus;

/**
 * Extraction spans used to be unproducible.
 *
 * The projector reconstructed them from `executionId` + `phaseId` payload keys, but the only
 * runtime emitter - ExtractingBuffer - wrote neither, so every extraction event fell through
 * the early return. These pin the envelope-first path and keep the id-only fallback honest,
 * since doctools reuses the same event classes for markdown extraction.
 */

const SO_ROOT = 'so-exec-1';
const EXTRACT_PHASE = 'so-exec-1:response.extraction:attempt-1';

function extractionHarness(): array
{
    $otel = new OtelExporter();
    $telemetry = new Telemetry(new TraceRegistry(), $otel);
    $projector = new InstructorTelemetryProjector($telemetry);

    // Open the structured-output root the extraction must nest under.
    $projector->project(new StructuredOutputStarted([
        'executionId' => SO_ROOT,
        'requestId' => 'so-req-1',
        'model' => 'gpt-test',
        'messageCount' => 1,
        'isStreamed' => false,
    ]));

    return [$projector, $telemetry, $otel];
}

function extractionContext(): PhaseTelemetryContext
{
    return new PhaseTelemetryContext(
        envelope: new TelemetryEnvelope(
            operation: new OperationDescriptor(
                id: EXTRACT_PHASE,
                type: 'structured_output.extraction',
                name: 'structured_output.extract',
                kind: OperationKind::Span,
            ),
            correlation: OperationCorrelation::child(
                rootOperationId: SO_ROOT,
                parentOperationId: SO_ROOT,
                requestId: 'so-req-1',
            ),
        ),
        executionId: SO_ROOT,
        phaseId: EXTRACT_PHASE,
        phase: 'response.extraction',
        attemptId: 'attempt-1',
    );
}

function observationNamed(OtelExporter $otel, string $name): ?object
{
    foreach ($otel->observations() as $observation) {
        if ($observation->name() === $name) {
            return $observation;
        }
    }
    return null;
}

it('opens the extraction span from the envelope and closes it on completion', function () {
    [$projector, $telemetry, $otel] = extractionHarness();
    $context = extractionContext();

    $projector->project(new ExtractionStarted([
        ...$context->eventData(),
        'content_length' => 42,
        'extractors_count' => 3,
    ]));

    // The span is open and keyed on the phase id, not the execution id.
    $span = $telemetry->spanReference(EXTRACT_PHASE);
    expect($span)->not->toBeNull();

    $projector->project(new ExtractionCompleted([
        ...$context->eventData(),
        'strategy' => 'DirectJsonExtractor',
        'content_length' => 42,
    ]));

    $observation = observationNamed($otel, 'structured_output.extract');
    expect($observation)->not->toBeNull()
        ->and($observation->status())->toBe(ObservationStatus::Ok);
});

it('nests the extraction span under the structured output root', function () {
    [$projector, $telemetry] = extractionHarness();

    $root = $telemetry->spanReference(SO_ROOT);
    $projector->project(new ExtractionStarted([...extractionContext()->eventData(), 'content_length' => 1]));
    $child = $telemetry->spanReference(EXTRACT_PHASE);

    expect($child?->traceId()->value())->toBe($root?->traceId()->value())
        ->and($child?->parentSpanId()?->value())->toBe($root?->spanId()->value())
        ->and($child?->spanId()->value())->not->toBe($root?->spanId()->value());
});

it('fails the extraction span when extraction fails', function () {
    [$projector, $_telemetry, $otel] = extractionHarness();
    $context = extractionContext();

    $projector->project(new ExtractionStarted([...$context->eventData(), 'content_length' => 7]));
    $projector->project(new ExtractionFailed([
        ...$context->eventData(),
        'strategies_tried' => ['DirectJsonExtractor'],
        'error' => 'DirectJsonExtractor: Syntax error',
    ]));

    $observation = observationNamed($otel, 'structured_output.extract');
    expect($observation?->status())->toBe(ObservationStatus::Error);
});

it('still projects id-only events from emitters that carry no envelope', function () {
    // doctools dispatches these classes for markdown extraction, and standalone
    // ResponseExtractor use has no execution to be a child of.
    [$projector, $telemetry] = extractionHarness();

    $projector->project(new ExtractionStarted([
        'executionId' => SO_ROOT,
        'phaseId' => 'legacy-phase',
        'phase' => 'response.extraction',
    ]));

    expect($telemetry->spanReference('legacy-phase'))->not->toBeNull();
});

it('ignores extraction events that carry neither envelope nor ids', function () {
    [$projector, $_telemetry, $otel] = extractionHarness();
    $before = count($otel->observations());

    // The pre-fix ExtractingBuffer payload, verbatim.
    $projector->project(new ExtractionStarted(['content_length' => 12, 'extractors_count' => 3]));
    $projector->project(new ExtractionCompleted(['strategy' => 'DirectJsonExtractor', 'content_length' => 12]));

    expect(count($otel->observations()))->toBe($before);
});
