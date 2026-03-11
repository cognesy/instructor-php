<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Broadcasting;

use Cognesy\Agents\Broadcasting\AgentBroadcastObserver;
use Cognesy\Agents\Broadcasting\BroadcastConfig;
use Cognesy\Agents\Broadcasting\CanBroadcastAgentEvents;
use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
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
 * Capability that exposes agent execution as UI-facing broadcast envelopes.
 */
final readonly class UseAgentBroadcasting implements CanProvideAgentCapability
{
    public function __construct(
        private CanBroadcastAgentEvents $broadcaster,
        private ?string $sessionId = null,
        private BroadcastConfig $config = new BroadcastConfig(),
    ) {}

    #[\Override]
    public static function capabilityName(): string
    {
        return 'use_agent_broadcasting';
    }

    #[\Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent
    {
        $observer = new AgentBroadcastObserver(
            transport: $this->broadcaster,
            sessionId: $this->sessionId,
            config: $this->config,
        );

        $events = $agent->events();
        $events->addListener(AgentExecutionStarted::class, [$observer, 'onAgentExecutionStarted']);
        $events->addListener(AgentExecutionCompleted::class, [$observer, 'onAgentExecutionCompleted']);
        $events->addListener(AgentExecutionFailed::class, [$observer, 'onAgentExecutionFailed']);
        $events->addListener(AgentStepStarted::class, [$observer, 'onAgentStepStarted']);
        $events->addListener(AgentStepCompleted::class, [$observer, 'onAgentStepCompleted']);
        $events->addListener(ToolCallStarted::class, [$observer, 'onToolCallStarted']);
        $events->addListener(ToolCallCompleted::class, [$observer, 'onToolCallCompleted']);
        $events->addListener(ContinuationEvaluated::class, [$observer, 'onContinuationEvaluated']);
        $events->addListener(InferenceRequestStarted::class, [$observer, 'onInferenceRequestStarted']);
        $events->addListener(PartialInferenceDeltaCreated::class, [$observer, 'onPartialInferenceDelta']);

        return $agent;
    }
}
