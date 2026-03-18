<?php declare(strict_types=1);

namespace Cognesy\Instructor\Telemetry;

use Cognesy\Instructor\Events\Extraction\ExtractionCompleted;
use Cognesy\Instructor\Events\Extraction\ExtractionFailed;
use Cognesy\Instructor\Events\Extraction\ExtractionStarted;
use Cognesy\Instructor\Events\Response\ResponseGenerationFailed;
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
            $event instanceof ResponseGenerationFailed => $this->onErrorLog($event, 'structured_output.response_generation_failed'),
            $event instanceof ResponseValidationFailed => $this->onErrorLog($event, 'structured_output.response_validation_failed'),
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

    private function onExtractionStarted(ExtractionStarted $event): void
    {
        $data = EventData::of($event);
        $phaseId = EventData::string($data, 'phaseId');
        $executionId = EventData::string($data, 'executionId');

        if ($phaseId === null || $executionId === null) {
            return;
        }

        $this->telemetry->openChild(
            key: $phaseId,
            parentKey: $executionId,
            name: 'structured_output.extract',
            attributes: $this->attributes([
                'structured_output.phase' => EventData::string($data, 'phase'),
                'structured_output.strategy' => EventData::string($data, 'strategy'),
            ]),
        );
    }

    private function onExtractionCompleted(ExtractionCompleted $event): void
    {
        $data = EventData::of($event);
        $phaseId = EventData::string($data, 'phaseId');
        if ($phaseId === null) {
            return;
        }

        $this->telemetry->complete($phaseId, $this->attributes([
            'structured_output.phase' => EventData::string($data, 'phase'),
            'structured_output.value_type' => EventData::string($data, 'valueType'),
        ]));
    }

    private function onExtractionFailed(ExtractionFailed $event): void
    {
        $data = EventData::of($event);
        $phaseId = EventData::string($data, 'phaseId');
        if ($phaseId === null) {
            return;
        }

        $this->telemetry->fail($phaseId, $this->attributes([
            'error.message' => EventData::string($data, 'error'),
            'structured_output.phase' => EventData::string($data, 'phase'),
        ]));
    }

    private function onErrorLog(object $event, string $name): void
    {
        $data = EventData::of($event);
        $executionId = EventData::string($data, 'executionId') ?? $name;

        $this->telemetry->log(
            key: $executionId,
            name: $name,
            attributes: $this->attributes([
                'error.message' => EventData::string($data, 'error'),
                'structured_output.phase' => EventData::string($data, 'phase'),
                'structured_output.phase_id' => EventData::string($data, 'phaseId'),
            ]),
            status: ObservationStatus::Error,
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
