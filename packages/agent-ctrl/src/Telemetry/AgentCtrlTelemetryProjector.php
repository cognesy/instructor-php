<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Telemetry;

use Cognesy\AgentCtrl\Event\AgentErrorOccurred;
use Cognesy\AgentCtrl\Event\AgentEvent;
use Cognesy\AgentCtrl\Event\AgentExecutionCompleted;
use Cognesy\AgentCtrl\Event\AgentExecutionStarted;
use Cognesy\Telemetry\Application\Projector\CanProjectTelemetry;
use Cognesy\Telemetry\Application\Projector\Support\EventData;
use Cognesy\Telemetry\Application\Telemetry;
use Cognesy\Telemetry\Domain\Envelope\OperationKind;
use Cognesy\Telemetry\Domain\Envelope\TelemetryEnvelope;
use Cognesy\Telemetry\Domain\Envelope\TelemetryEnvelopeAttributes;
use Cognesy\Telemetry\Domain\Value\AttributeBag;

final readonly class AgentCtrlTelemetryProjector implements CanProjectTelemetry
{
    public function __construct(
        private Telemetry $telemetry,
    ) {}

    #[\Override]
    public function project(object $event): void
    {
        if (!$event instanceof AgentEvent) {
            return;
        }

        $data = EventData::of($event);
        $executionKey = (string) $event->executionId();
        $attributes = $this->attributes($event, $data);
        $envelope = EventData::telemetry($data);

        match (true) {
            $event instanceof AgentExecutionStarted && $envelope !== null => $this->openEnvelope($envelope, $attributes),
            $event instanceof AgentExecutionStarted => $this->openExecution($executionKey, $attributes),
            $event instanceof AgentExecutionCompleted && $envelope !== null => $this->completeEnvelope($envelope, $attributes),
            $event instanceof AgentExecutionCompleted => $this->telemetry->complete($executionKey, $attributes),
            $event instanceof AgentErrorOccurred && $envelope !== null => $this->failEnvelope($envelope, $attributes),
            $event instanceof AgentErrorOccurred => $this->telemetry->fail($executionKey, $attributes),
            $envelope !== null => $this->logEnvelope($envelope, $attributes),
            default => null,
        };
    }

    /** @param array<string, mixed> $data */
    private function openExecution(string $executionKey, AttributeBag $attributes): void
    {
        if ($this->telemetry->spanReference($executionKey) !== null) {
            return;
        }

        $this->telemetry->openRoot($executionKey, 'agent_ctrl.execute', attributes: $attributes);
    }

    /** @param array<string, mixed> $data */
    private function attributes(AgentEvent $event, array $data): AttributeBag
    {
        return $this->bag([
            'agent_ctrl.agent_type' => EventData::string($data, 'agentType'),
            'agent_ctrl.execution_id' => (string) $event->executionId(),
            'agent_ctrl.session_id' => EventData::string($data, 'sessionId'),
            'agent_ctrl.event' => $event->name(),
            'agent_ctrl.prompt' => EventData::string($data, 'prompt'),
            'agent_ctrl.model' => EventData::string($data, 'model'),
            'agent_ctrl.working_directory' => EventData::string($data, 'workingDirectory'),
            'agent_ctrl.request_type' => EventData::string($data, 'requestType'),
            'agent_ctrl.tool' => EventData::string($data, 'tool'),
            'agent_ctrl.call_id' => EventData::string($data, 'callId'),
            'agent_ctrl.error' => EventData::string($data, 'error'),
            'agent_ctrl.error_class' => EventData::string($data, 'errorClass'),
            'agent_ctrl.exit_code' => EventData::int($data, 'exitCode'),
            'agent_ctrl.input_tokens' => EventData::int($data, 'inputTokens'),
            'agent_ctrl.output_tokens' => EventData::int($data, 'outputTokens'),
            'agent_ctrl.tool_call_count' => EventData::int($data, 'toolCallCount'),
            'agent_ctrl.command_count' => EventData::int($data, 'commandCount'),
            'agent_ctrl.argv_count' => EventData::int($data, 'argvCount'),
            'agent_ctrl.attempt_number' => EventData::int($data, 'attemptNumber'),
            'agent_ctrl.total_attempts' => EventData::int($data, 'totalAttempts'),
            'agent_ctrl.success_attempt' => EventData::int($data, 'successAttempt'),
            'agent_ctrl.event_count' => EventData::int($data, 'eventCount'),
            'agent_ctrl.tool_use_count' => EventData::int($data, 'toolUseCount'),
            'agent_ctrl.text_length' => EventData::int($data, 'textLength'),
            'agent_ctrl.response_size' => EventData::int($data, 'responseSize'),
            'agent_ctrl.chunk_number' => EventData::int($data, 'chunkNumber'),
            'agent_ctrl.chunk_size' => EventData::int($data, 'chunkSize'),
            'agent_ctrl.content_type' => EventData::string($data, 'contentType'),
            'agent_ctrl.driver' => EventData::string($data, 'driver'),
            'agent_ctrl.format' => EventData::string($data, 'format'),
            'agent_ctrl.timeout' => EventData::int($data, 'timeout'),
            'agent_ctrl.network_enabled' => EventData::bool($data, 'networkEnabled'),
            'agent_ctrl.cost_usd' => EventData::float($data, 'cost'),
            'agent_ctrl.command_duration_ms' => EventData::float($data, 'commandDurationMs'),
            'agent_ctrl.build_duration_ms' => EventData::float($data, 'buildDurationMs'),
            'agent_ctrl.execution_duration_ms' => EventData::float($data, 'executionDurationMs'),
            'agent_ctrl.total_execution_duration_ms' => EventData::float($data, 'totalExecutionDurationMs'),
            'agent_ctrl.total_duration_ms' => EventData::float($data, 'totalDurationMs'),
            'agent_ctrl.processing_duration_ms' => EventData::float($data, 'processingDurationMs'),
            'agent_ctrl.extract_duration_ms' => EventData::float($data, 'extractDurationMs'),
            'agent_ctrl.configure_duration_ms' => EventData::float($data, 'configureDurationMs'),
            'agent_ctrl.initialization_duration_ms' => EventData::float($data, 'initializationDurationMs'),
            'agent_ctrl.total_setup_duration_ms' => EventData::float($data, 'totalSetupDurationMs'),
        ]);
    }

    /** @param array<string, scalar|array<array-key, scalar>|null> $items */
    private function bag(array $items): AttributeBag
    {
        return AttributeBag::fromArray(array_filter($items, static fn(mixed $value): bool => $value !== null));
    }

    private function openEnvelope(TelemetryEnvelope $envelope, AttributeBag $attributes): void
    {
        if ($this->telemetry->spanReference($envelope->operation()->id()) !== null) {
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

        match ($envelope->operation()->kind()) {
            OperationKind::RootSpan => $this->telemetry->openRoot(
                $envelope->operation()->id(),
                $envelope->operation()->name(),
                $envelope->trace(),
                $this->envelopeAttributes($envelope)->merge($attributes),
            ),
            OperationKind::Span => match ($resolvedParent) {
                null => $this->telemetry->openRoot(
                    $envelope->operation()->id(),
                    $envelope->operation()->name(),
                    $envelope->trace(),
                    $this->envelopeAttributes($envelope)->merge($attributes),
                ),
                default => $this->telemetry->openChild(
                    $envelope->operation()->id(),
                    $resolvedParent,
                    $envelope->operation()->name(),
                    $this->envelopeAttributes($envelope)->merge($attributes),
                ),
            },
            OperationKind::Event => match ($resolvedParent) {
                null => null,
                default => $this->telemetry->log(
                    $resolvedParent,
                    $envelope->operation()->name(),
                    $this->envelopeAttributes($envelope)->merge($attributes),
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

    private function failEnvelope(TelemetryEnvelope $envelope, AttributeBag $attributes): void
    {
        $this->telemetry->fail(
            $envelope->operation()->id(),
            $this->envelopeAttributes($envelope)->merge($attributes),
        );
    }

    private function logEnvelope(TelemetryEnvelope $envelope, AttributeBag $attributes): void
    {
        $parentKey = $envelope->correlation()->parentOperationId() ?? $envelope->correlation()->rootOperationId();
        if ($this->telemetry->spanReference($parentKey) === null) {
            return;
        }

        $this->telemetry->log(
            $parentKey,
            $envelope->operation()->name(),
            $this->envelopeAttributes($envelope)->merge($attributes),
        );
    }

    private function envelopeAttributes(TelemetryEnvelope $envelope): AttributeBag
    {
        return TelemetryEnvelopeAttributes::fromEnvelope($envelope);
    }
}
