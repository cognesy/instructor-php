<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Contracts\CanDetermineRetry;
use Cognesy\Instructor\Core\AttemptProcessor;
use Cognesy\Instructor\Core\ResponseGenerator;
use Cognesy\Instructor\Data\StructuredOutputAttempt;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Enums\ExecutionStatus;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\Events\Extraction\ExtractionCompleted;
use Cognesy\Instructor\Events\Extraction\ExtractionFailed;
use Cognesy\Instructor\Events\Extraction\ExtractionStarted;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Extraction\ResponseExtractor;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Instructor\Telemetry\InstructorTelemetryProjector;
use Cognesy\Instructor\Telemetry\StructuredOutputTelemetry;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Telemetry\Adapters\OTel\OtelExporter;
use Cognesy\Telemetry\Application\Registry\TraceRegistry;
use Cognesy\Telemetry\Application\Telemetry;
use Cognesy\Telemetry\Domain\Envelope\TelemetryEnvelope;
use Cognesy\Utils\Result\Result;

/**
 * The runtime half of the extraction-telemetry fix.
 *
 * ExtractionEnvelopeProjectionTest proves the projector consumes envelopes; this proves the
 * live flow actually produces them. Before the fix ExtractingBuffer dispatched bare payloads
 * with no ids at all, so no amount of projector work could have built the span.
 */

function runningExecution(): StructuredOutputExecution
{
    return new StructuredOutputExecution(
        request: new StructuredOutputRequest(
            messages: Messages::fromString('Extract user'),
            requestedSchema: ['type' => 'object'],
        ),
        config: new StructuredOutputConfig(outputMode: OutputMode::Json),
        responseModel: makeAnyResponseModel(stdClass::class),
        activeAttempt: new StructuredOutputAttempt(isFinalized: false),
        status: ExecutionStatus::Running,
    );
}

/** Drives a real AttemptProcessor over a real ResponseExtractor and captures what was emitted. */
function captureExtractionEvents(string $content): array
{
    $captured = [];
    $events = new EventDispatcher('test');
    $events->wiretap(function (object $event) use (&$captured): void {
        $captured[] = $event;
    });

    $execution = runningExecution();
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
        $processor->processInferenceResponse($execution, new InferenceResponse(content: $content));
    } catch (Throwable) {
        // Extraction failure is one of the cases under test; the events are what matter.
    }

    return [$captured, $execution];
}

function firstEvent(array $events, string $class): ?object
{
    foreach ($events as $event) {
        if ($event instanceof $class) {
            return $event;
        }
    }
    return null;
}

it('stamps extraction started with an envelope parented to the execution root', function () {
    [$captured, $execution] = captureExtractionEvents('{"name":"Ann"}');

    $started = firstEvent($captured, ExtractionStarted::class);
    expect($started)->not->toBeNull();

    $envelope = TelemetryEnvelope::fromArray($started->data[TelemetryEnvelope::KEY]);
    $executionId = $execution->id()->toString();

    expect($envelope->operation()->name())->toBe('structured_output.extract')
        ->and($envelope->operation()->id())->toBe(StructuredOutputTelemetry::phaseId($execution, 'response.extraction'))
        ->and($envelope->correlation()->rootOperationId())->toBe($executionId)
        ->and($envelope->correlation()->parentOperationId())->toBe($executionId);
});

it('stamps completed with the same span key it opened under', function () {
    [$captured] = captureExtractionEvents('{"name":"Ann"}');

    $started = firstEvent($captured, ExtractionStarted::class);
    $completed = firstEvent($captured, ExtractionCompleted::class);

    expect($completed)->not->toBeNull()
        ->and($completed->data['phaseId'])->toBe($started->data['phaseId'])
        ->and($completed->data[TelemetryEnvelope::KEY]['operation']['id'])
        ->toBe($started->data[TelemetryEnvelope::KEY]['operation']['id']);
});

it('stamps failed extraction and carries a readable error', function () {
    [$captured] = captureExtractionEvents('this is not json at all');

    $failed = firstEvent($captured, ExtractionFailed::class);

    expect($failed)->not->toBeNull()
        ->and($failed->data)->toHaveKey(TelemetryEnvelope::KEY)
        ->and($failed->data['error'])->toBeString();
});

it('projects the live event stream into a nested extraction span', function () {
    $otel = new OtelExporter();
    $telemetry = new Telemetry(new TraceRegistry(), $otel);
    $projector = new InstructorTelemetryProjector($telemetry);

    [$captured, $execution] = captureExtractionEvents('{"name":"Ann"}');
    $executionId = $execution->id()->toString();

    // Open the root the same way the runtime does, then replay what extraction emitted.
    $telemetry->openRoot($executionId, 'structured_output.execute');
    foreach ($captured as $event) {
        $projector->project($event);
    }

    // The extraction span is completed by the replay, so read the exported observation
    // rather than the registry - completing removes the span from it.
    $root = $telemetry->spanReference($executionId);
    $exported = null;
    foreach ($otel->observations() as $observation) {
        if ($observation->name() === 'structured_output.extract') {
            $exported = $observation;
        }
    }

    expect($exported)->not->toBeNull()
        ->and($exported->spanReference()->traceId()->value())->toBe($root->traceId()->value())
        ->and($exported->spanReference()->parentSpanId()?->value())->toBe($root->spanId()->value());
});
