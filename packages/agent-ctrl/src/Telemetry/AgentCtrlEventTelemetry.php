<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Telemetry;

use Cognesy\AgentCtrl\Event\AgentErrorOccurred;
use Cognesy\AgentCtrl\Event\AgentEvent;
use Cognesy\AgentCtrl\Event\AgentExecutionCompleted;
use Cognesy\AgentCtrl\Event\AgentExecutionStarted;
use Cognesy\AgentCtrl\Event\AgentTextReceived;
use Cognesy\AgentCtrl\Event\AgentToolUsed;
use Cognesy\Telemetry\Domain\Envelope\OperationCorrelation;
use Cognesy\Telemetry\Domain\Envelope\OperationDescriptor;
use Cognesy\Telemetry\Domain\Envelope\OperationIO;
use Cognesy\Telemetry\Domain\Envelope\OperationKind;
use Cognesy\Telemetry\Domain\Envelope\TelemetryEnvelope;

final readonly class AgentCtrlEventTelemetry
{
    public static function attach(AgentEvent $event): AgentEvent
    {
        $envelope = self::envelopeFor($event);
        if ($envelope === null) {
            return $event;
        }

        $event->data = [
            ...is_array($event->data) ? $event->data : [],
            TelemetryEnvelope::KEY => $envelope->toArray(),
        ];

        return $event;
    }

    private static function envelopeFor(AgentEvent $event): ?TelemetryEnvelope
    {
        $executionId = (string) $event->executionId();
        $sessionId = is_string($event->data['sessionId'] ?? null) ? $event->data['sessionId'] : null;

        return match (true) {
            $event instanceof AgentExecutionStarted => (new TelemetryEnvelope(
                operation: new OperationDescriptor(
                    id: $executionId,
                    type: 'agent_ctrl.execution',
                    name: 'agent_ctrl.execute',
                    kind: OperationKind::RootSpan,
                ),
                correlation: OperationCorrelation::root(
                    operationId: $executionId,
                    sessionId: $sessionId,
                ),
            ))->withIO(new OperationIO(
                input: array_filter([
                    'prompt' => $event->prompt,
                    'model' => $event->model,
                    'working_directory' => $event->workingDirectory,
                ], static fn(mixed $v): bool => $v !== null),
            )),
            $event instanceof AgentExecutionCompleted => (new TelemetryEnvelope(
                operation: new OperationDescriptor(
                    id: $executionId,
                    type: 'agent_ctrl.execution',
                    name: 'agent_ctrl.execute',
                    kind: OperationKind::Span,
                ),
                correlation: OperationCorrelation::root(
                    operationId: $executionId,
                    sessionId: $sessionId,
                ),
            ))->withIO(new OperationIO(
                output: $event->text !== '' ? $event->text : array_filter([
                    'exit_code' => $event->exitCode,
                    'tool_call_count' => $event->toolCallCount,
                    'cost' => $event->cost,
                    'input_tokens' => $event->inputTokens,
                    'output_tokens' => $event->outputTokens,
                ], static fn(mixed $v): bool => $v !== null),
            )),
            $event instanceof AgentErrorOccurred => new TelemetryEnvelope(
                operation: new OperationDescriptor(
                    id: $executionId,
                    type: 'agent_ctrl.execution',
                    name: 'agent_ctrl.execute',
                    kind: OperationKind::Span,
                ),
                correlation: OperationCorrelation::root(
                    operationId: $executionId,
                    sessionId: $sessionId,
                ),
            ),
            $event instanceof AgentToolUsed => (new TelemetryEnvelope(
                operation: new OperationDescriptor(
                    id: $event->id,
                    type: 'agent_ctrl.event',
                    name: 'agent_ctrl.' . $event->name(),
                    kind: OperationKind::Event,
                ),
                correlation: OperationCorrelation::child(
                    rootOperationId: $executionId,
                    parentOperationId: $executionId,
                    sessionId: $sessionId,
                ),
            ))->withIO(new OperationIO(
                input: ['tool' => $event->tool, 'input' => $event->input],
                output: $event->output,
            )),
            $event instanceof AgentTextReceived => (new TelemetryEnvelope(
                operation: new OperationDescriptor(
                    id: $event->id,
                    type: 'agent_ctrl.event',
                    name: 'agent_ctrl.' . $event->name(),
                    kind: OperationKind::Event,
                ),
                correlation: OperationCorrelation::child(
                    rootOperationId: $executionId,
                    parentOperationId: $executionId,
                    sessionId: $sessionId,
                ),
            ))->withIO(new OperationIO(
                output: $event->text,
            )),
            default => null,
        };
    }
}
