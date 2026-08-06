<?php declare(strict_types=1);

namespace Cognesy\Instructor\Telemetry;

use Cognesy\Instructor\Events\Extraction\ExtractionCompleted;
use Cognesy\Instructor\Events\Extraction\ExtractionFailed;
use Cognesy\Instructor\Events\Extraction\ExtractionStarted;
use Cognesy\Instructor\Events\Attempt\ResponseRecoveryExhausted;
use Cognesy\Instructor\Events\Attempt\ResponseRetryScheduled;
use Cognesy\Instructor\Events\Response\CustomResponseValidationAttempt;
use Cognesy\Instructor\Events\Response\ResponseMaterializationFailed;
use Cognesy\Instructor\Events\Response\ResponseMaterialized;
use Cognesy\Instructor\Events\Response\ResponseValidated;
use Cognesy\Instructor\Events\Response\ResponseValidationAttempt;
use Cognesy\Instructor\Events\Response\ResponseValidationFailed;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputRequestReceived;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseGenerated;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputStarted;
use Cognesy\Telemetry\Application\Projector\CanProjectTelemetry;
use Cognesy\Telemetry\Application\Projector\Support\EventData;
use Cognesy\Telemetry\Application\Telemetry;
use Cognesy\Telemetry\Domain\Envelope\OperationKind;
use Cognesy\Telemetry\Domain\Envelope\TelemetryEnvelope;
use Cognesy\Telemetry\Domain\Envelope\TelemetryEnvelopeAttributes;
use Cognesy\Telemetry\Domain\Observation\ObservationStatus;
use Cognesy\Telemetry\Domain\Value\AttributeBag;

final readonly class InstructorTelemetryProjector implements CanProjectTelemetry
{
    public function __construct(
        private Telemetry $telemetry,
    ) {}

    #[\Override]
    public function project(object $event): void
    {
        match (true) {
            $event instanceof StructuredOutputRequestReceived => $this->onStructuredOutputStarted($event),
            $event instanceof StructuredOutputStarted => $this->onStructuredOutputStarted($event),
            $event instanceof StructuredOutputResponseGenerated => $this->onStructuredOutputCompleted($event),
            $event instanceof ExtractionStarted => $this->onExtractionStarted($event),
            $event instanceof ExtractionCompleted => $this->onExtractionCompleted($event),
            $event instanceof ExtractionFailed => $this->onExtractionFailed($event),
            $event instanceof ResponseMaterialized => $this->onEventLog($event, 'structured_output.response_materialized'),
            $event instanceof ResponseRetryScheduled => $this->onEventLog($event, 'structured_output.response_retry_scheduled'),
            $event instanceof ResponseMaterializationFailed => $this->onErrorLog($event, 'structured_output.response_materialization_failed'),
            $event instanceof ResponseRecoveryExhausted => $this->onErrorLog($event, 'structured_output.response_recovery_exhausted'),
            $event instanceof ResponseValidationAttempt => $this->onValidationStarted($event),
            $event instanceof CustomResponseValidationAttempt => $this->onValidationStarted($event),
            $event instanceof ResponseValidated => $this->onValidationCompleted($event),
            $event instanceof ResponseValidationFailed => $this->onValidationFailed($event),
            default => null,
        };
    }

    private function onStructuredOutputStarted(object $event): void
    {
        $data = EventData::of($event);
        $envelope = EventData::telemetry($data);

        if ($envelope !== null) {
            $this->openEnvelope($envelope, $this->attributes([
                'inference.request.id' => EventData::string($data, 'requestId'),
                'inference.response.model' => EventData::string($data, 'model'),
                'inference.request.is_streamed' => EventData::bool($data, 'isStreamed'),
                'inference.request.message_count' => EventData::int($data, 'messageCount'),
            ]));

            return;
        }

        $executionId = EventData::string($data, 'executionId');
        if ($executionId === null || $this->telemetry->spanReference($executionId) !== null) {
            return;
        }

        $this->telemetry->openRoot(
            key: $executionId,
            name: 'structured_output.execute',
            attributes: $this->attributes([
                'inference.request.id' => EventData::string($data, 'requestId'),
                'inference.response.model' => EventData::string($data, 'model'),
                'inference.request.is_streamed' => EventData::bool($data, 'isStreamed'),
                'inference.request.message_count' => EventData::int($data, 'messageCount'),
            ]),
        );
    }

    private function onStructuredOutputCompleted(StructuredOutputResponseGenerated $event): void
    {
        $data = EventData::of($event);
        $envelope = EventData::telemetry($data);

        if ($envelope !== null) {
            $this->completeEnvelope($envelope, $this->attributes([
                'structured_output.phase' => EventData::string($data, 'phase'),
                'structured_output.value_type' => EventData::string($data, 'valueType'),
                'structured_output.has_value' => EventData::bool($data, 'hasValue'),
                'inference.finish_reason' => EventData::string($data, 'finishReason'),
                'inference.tokens.total' => EventData::int($data, 'totalTokens'),
            ]));

            return;
        }

        $executionId = EventData::string($data, 'executionId');
        if ($executionId === null) {
            return;
        }

        $this->telemetry->complete($executionId, $this->attributes([
            'structured_output.phase' => EventData::string($data, 'phase'),
            'structured_output.value_type' => EventData::string($data, 'valueType'),
            'structured_output.has_value' => EventData::bool($data, 'hasValue'),
            'inference.finish_reason' => EventData::string($data, 'finishReason'),
            'inference.tokens.total' => EventData::int($data, 'totalTokens'),
        ]));
    }

    /**
     * Extraction events now arrive stamped by AttemptProcessor, so the span opens from the
     * envelope. The id-only branch is kept for emitters that never gained a context - notably
     * standalone `ResponseExtractor` use and doctools, which reuses these event classes for
     * markdown extraction - and for events replayed from older logs.
     */
    private function onExtractionStarted(ExtractionStarted $event): void
    {
        $data = EventData::of($event);
        $attributes = $this->attributes([
            'structured_output.phase' => EventData::string($data, 'phase'),
            'structured_output.strategy' => EventData::string($data, 'strategy'),
            'structured_output.content_length' => EventData::int($data, 'content_length'),
        ]);

        $envelope = EventData::telemetry($data);
        if ($envelope !== null) {
            $this->openEnvelope($envelope, $attributes);
            return;
        }

        $phaseId = EventData::string($data, 'phaseId');
        $executionId = EventData::string($data, 'executionId');
        if ($phaseId === null || $executionId === null) {
            return;
        }

        $this->telemetry->openChild(
            key: $phaseId,
            parentKey: $executionId,
            name: 'structured_output.extract',
            attributes: $attributes,
        );
    }

    private function onExtractionCompleted(ExtractionCompleted $event): void
    {
        $data = EventData::of($event);
        $key = $this->extractionKey($data);
        if ($key === null) {
            return;
        }

        $this->telemetry->complete($key, $this->attributes([
            'structured_output.phase' => EventData::string($data, 'phase'),
            'structured_output.strategy' => EventData::string($data, 'strategy'),
            'structured_output.value_type' => EventData::string($data, 'valueType'),
        ]));
    }

    private function onExtractionFailed(ExtractionFailed $event): void
    {
        $data = EventData::of($event);
        $key = $this->extractionKey($data);
        if ($key === null) {
            return;
        }

        $this->telemetry->fail($key, $this->attributes([
            'error.message' => EventData::string($data, 'error'),
            'structured_output.phase' => EventData::string($data, 'phase'),
        ]));
    }

    /**
     * The span key for a completed/failed extraction: the envelope's operation id, which
     * AttemptProcessor sets to the same phase id the started event opened under.
     *
     * @param array<string, mixed> $data
     */
    private function extractionKey(array $data): ?string
    {
        return EventData::telemetry($data)?->operation()->id()
            ?? EventData::string($data, 'phaseId');
    }

    /**
     * `ResponseValidator` already emitted a genuine open/close pair - an attempt event followed
     * by validated-or-failed - so the span is those events rather than a second lifecycle laid
     * over them. The attempt opens it.
     *
     * No id-only fallback here: unlike extraction, these events never carried ids of their own,
     * so an unstamped attempt has nothing to be a child of. It stays invisible, exactly as it
     * was before, rather than becoming a stray root.
     */
    private function onValidationStarted(object $event): void
    {
        $data = EventData::of($event);
        $envelope = EventData::telemetry($data);
        if ($envelope === null) {
            return;
        }

        $this->openEnvelope($envelope, $this->attributes([
            'structured_output.phase' => EventData::string($data, 'phase'),
            'structured_output.response_class' => EventData::string($data, 'responseClass'),
            'structured_output.field_count' => EventData::int($data, 'fieldCount'),
            'structured_output.validator' => EventData::string($data, 'validator'),
        ]));
    }

    private function onValidationCompleted(ResponseValidated $event): void
    {
        $data = EventData::of($event);
        $envelope = EventData::telemetry($data);
        if ($envelope === null) {
            return;
        }

        $this->completeEnvelope($envelope, $this->attributes([
            'structured_output.validation.is_valid' => true,
            'structured_output.validation.error_count' => $this->validationErrorCount($data),
        ]));
    }

    /**
     * Failure closes the span with an error status. The pre-existing error log is kept only for
     * unstamped emitters - logging it as well when the span exists would say the same thing
     * twice in the same trace.
     */
    private function onValidationFailed(ResponseValidationFailed $event): void
    {
        $data = EventData::of($event);
        $envelope = EventData::telemetry($data);
        if ($envelope === null) {
            $this->onErrorLog($event, 'structured_output.response_validation_failed');
            return;
        }

        $this->telemetry->fail($envelope->operation()->id(), $this->attributes([
            'error.message' => EventData::string($data, 'errorMessage'),
            'error.type' => EventData::string($data, 'errorType'),
            'structured_output.validation.is_valid' => false,
            'structured_output.validation.error_count' => $this->validationErrorCount($data),
        ]));
    }

    /**
     * Counted rather than serialized: the fields and values that failed are already in the
     * validation event for anyone listening, and copying them onto the span would put user data
     * into telemetry that no capture policy was asked about.
     *
     * @param array<string, mixed> $data
     */
    private function validationErrorCount(array $data): ?int
    {
        $validation = $data['validation'] ?? null;
        if (!is_array($validation) || !is_array($validation['errors'] ?? null)) {
            return null;
        }

        return count($validation['errors']);
    }

    private function onErrorLog(object $event, string $name): void
    {
        $data = EventData::of($event);
        $executionId = EventData::string($data, 'executionId') ?? $name;
        $errorMessage = EventData::string($data, 'errorMessage')
            ?? EventData::string($data, 'error');

        $this->telemetry->log(
            key: $executionId,
            name: $name,
            attributes: $this->attributes([
                'error.message' => $errorMessage,
                'error.type' => EventData::string($data, 'errorType'),
                'structured_output.failure_stage' => EventData::string($data, 'stage'),
                'structured_output.phase' => EventData::string($data, 'phase'),
                'structured_output.phase_id' => EventData::string($data, 'phaseId'),
            ]),
            status: ObservationStatus::Error,
        );
    }

    private function onEventLog(object $event, string $name): void
    {
        $data = EventData::of($event);
        $executionId = EventData::string($data, 'executionId');
        if ($executionId === null) {
            return;
        }

        $this->telemetry->log(
            key: $executionId,
            name: $name,
            attributes: $this->attributes([
                'structured_output.phase' => EventData::string($data, 'phase'),
                'structured_output.phase_id' => EventData::string($data, 'phaseId'),
                'structured_output.failure_stage' => EventData::string($data, 'stage'),
                'structured_output.result_type' => EventData::string($data, 'resultType'),
            ]),
        );
    }

    /** @param array<string, scalar|array<array-key, scalar>|null> $items */
    private function attributes(array $items): AttributeBag
    {
        return AttributeBag::fromArray(array_filter($items, static fn(mixed $value): bool => $value !== null));
    }

    private function openEnvelope(TelemetryEnvelope $envelope, AttributeBag $attributes): void
    {
        $operation = $envelope->operation();

        if ($this->telemetry->spanReference($operation->id()) !== null) {
            return;
        }

        $correlation = $envelope->correlation();
        $parentKey = $correlation->parentOperationId() ?? $correlation->rootOperationId();
        $rootKey = $correlation->rootOperationId();
        $resolvedParent = match (true) {
            $this->telemetry->spanReference($parentKey) !== null => $parentKey,
            $parentKey !== $rootKey && $this->telemetry->spanReference($rootKey) !== null => $rootKey,
            default => null,
        };

        match ($operation->kind()) {
            OperationKind::RootSpan => $this->telemetry->openRoot(
                key: $operation->id(),
                name: $operation->name(),
                context: $envelope->trace(),
                attributes: $this->envelopeAttributes($envelope)->merge($attributes),
            ),
            OperationKind::Span => match ($resolvedParent) {
                null => $this->telemetry->openRoot(
                    key: $operation->id(),
                    name: $operation->name(),
                    context: $envelope->trace(),
                    attributes: $this->envelopeAttributes($envelope)->merge($attributes),
                ),
                default => $this->telemetry->openChild(
                    key: $operation->id(),
                    parentKey: $resolvedParent,
                    name: $operation->name(),
                    attributes: $this->envelopeAttributes($envelope)->merge($attributes),
                ),
            },
            OperationKind::Event => match ($resolvedParent) {
                null => null,
                default => $this->telemetry->log(
                    key: $resolvedParent,
                    name: $operation->name(),
                    attributes: $this->envelopeAttributes($envelope)->merge($attributes),
                ),
            },
            default => null,
        };
    }

    private function completeEnvelope(TelemetryEnvelope $envelope, AttributeBag $attributes): void
    {
        $this->telemetry->complete(
            $envelope->operation()->id(),
            $this->envelopeAttributes($envelope)->merge($attributes),
        );
    }

    private function envelopeAttributes(TelemetryEnvelope $envelope): AttributeBag
    {
        return TelemetryEnvelopeAttributes::fromEnvelope($envelope);
    }
}
