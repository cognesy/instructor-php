<?php declare(strict_types=1);

namespace Cognesy\Agents\Broadcasting;

use Cognesy\Agents\Events\AgentExecutionCompleted;
use Cognesy\Agents\Events\AgentExecutionFailed;
use Cognesy\Agents\Events\AgentExecutionStarted;
use Cognesy\Agents\Events\AgentStepCompleted;
use Cognesy\Agents\Events\AgentStepStarted;
use Cognesy\Agents\Events\ContinuationEvaluated;
use Cognesy\Agents\Events\InferenceRequestStarted;
use Cognesy\Agents\Events\ToolCallCompleted;
use Cognesy\Agents\Events\ToolCallStarted;
use Cognesy\Polyglot\Inference\Events\PartialInferenceDeltaCreated;

/**
 * Coordinates per-execution broadcasters for UI-facing agent observation.
 */
final class AgentBroadcastObserver
{
    /** @var array<string, AgentEventBroadcaster> */
    private array $broadcasters = [];
    /** @var array<string, string> */
    private array $agentExecutionIdByInferenceExecutionId = [];

    public function __construct(
        private readonly CanBroadcastAgentEvents $transport,
        private readonly ?string $sessionId = null,
        private readonly BroadcastConfig $config = new BroadcastConfig(),
    ) {}

    public function onAgentExecutionStarted(AgentExecutionStarted $event): void
    {
        $this->broadcasters[$event->executionId] = new AgentEventBroadcaster(
            broadcaster: $this->transport,
            sessionId: $this->sessionId ?? $event->agentId,
            executionId: $event->executionId,
            config: $this->config,
        );
    }

    public function onInferenceRequestStarted(InferenceRequestStarted $event): void
    {
        if ($event->inferenceExecutionId === null) {
            return;
        }

        $this->agentExecutionIdByInferenceExecutionId[$event->inferenceExecutionId] = $event->executionId;
    }

    public function onPartialInferenceDelta(PartialInferenceDeltaCreated $event): void
    {
        $agentExecutionId = $this->agentExecutionIdByInferenceExecutionId[$event->executionId] ?? null;
        if ($agentExecutionId === null) {
            return;
        }

        $broadcaster = $this->broadcasterFor($agentExecutionId);
        if ($broadcaster === null) {
            return;
        }

        $broadcaster->onPartialInferenceDelta($event);
    }

    public function onAgentStepStarted(AgentStepStarted $event): void
    {
        $this->broadcasterFor($event->executionId)?->onAgentStepStarted($event);
    }

    public function onAgentStepCompleted(AgentStepCompleted $event): void
    {
        $this->broadcasterFor($event->executionId)?->onAgentStepCompleted($event);
    }

    public function onToolCallStarted(ToolCallStarted $event): void
    {
        $this->broadcasterFor($event->executionId)?->onToolCallStarted($event);
    }

    public function onToolCallCompleted(ToolCallCompleted $event): void
    {
        $this->broadcasterFor($event->executionId)?->onToolCallCompleted($event);
    }

    public function onContinuationEvaluated(ContinuationEvaluated $event): void
    {
        $this->broadcasterFor($event->executionId)?->onContinuationEvaluated($event);
    }

    public function onAgentExecutionFailed(AgentExecutionFailed $event): void
    {
        $this->broadcasterFor($event->executionId)?->onAgentExecutionFailed($event);
    }

    public function onAgentExecutionCompleted(AgentExecutionCompleted $event): void
    {
        unset($this->broadcasters[$event->executionId]);
        $this->removeInferenceMappingsFor($event->executionId);
    }

    private function broadcasterFor(string $executionId): ?AgentEventBroadcaster
    {
        return $this->broadcasters[$executionId] ?? null;
    }

    private function removeInferenceMappingsFor(string $executionId): void
    {
        foreach ($this->agentExecutionIdByInferenceExecutionId as $inferenceExecutionId => $agentExecutionId) {
            if ($agentExecutionId !== $executionId) {
                continue;
            }

            unset($this->agentExecutionIdByInferenceExecutionId[$inferenceExecutionId]);
        }
    }
}
