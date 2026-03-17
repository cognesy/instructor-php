<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Broadcasting\AgentBroadcastObserver;
use Cognesy\Agents\Broadcasting\BroadcastConfig;
use Cognesy\Agents\Broadcasting\CanBroadcastAgentEvents;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Agents\Events\AgentExecutionCompleted;
use Cognesy\Agents\Events\AgentExecutionFailed;
use Cognesy\Agents\Events\AgentExecutionStarted;
use Cognesy\Agents\Events\InferenceRequestStarted;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Polyglot\Inference\Events\PartialInferenceDeltaCreated;

it('maps inference stream chunks onto the active agent execution', function () {
    $transport = new class implements CanBroadcastAgentEvents {
        /** @var array<int, array{channel: string, envelope: array}> */
        public array $calls = [];

        public function broadcast(string $channel, array $envelope): void
        {
            $this->calls[] = ['channel' => $channel, 'envelope' => $envelope];
        }
    };

    $observer = new AgentBroadcastObserver($transport, 'session-1', BroadcastConfig::standard());

    $observer->onAgentExecutionStarted(new AgentExecutionStarted(
        agentId: 'agent-1',
        executionId: 'exec-1',
        parentAgentId: null,
        messageCount: 1,
        availableTools: 0,
    ));

    $observer->onInferenceRequestStarted(new InferenceRequestStarted(
        agentId: 'agent-1',
        executionId: 'exec-1',
        parentAgentId: null,
        stepNumber: 1,
        messageCount: 1,
        model: 'gpt-5-mini',
        inferenceExecutionId: 'inference-1',
    ));

    $observer->onPartialInferenceDelta(new PartialInferenceDeltaCreated([
        'executionId' => 'inference-1',
        'contentDelta' => 'Hello',
    ]));

    expect($transport->calls)->toHaveCount(1);
    expect($transport->calls[0]['channel'])->toBe('agent.session-1');
    expect($transport->calls[0]['envelope']['type'])->toBe('agent.stream.chunk');
    expect($transport->calls[0]['envelope']['execution_id'])->toBe('exec-1');
    expect($transport->calls[0]['envelope']['payload']['content'])->toBe('Hello');
});

it('cleans up execution routing after completion', function () {
    $transport = new class implements CanBroadcastAgentEvents {
        /** @var array<int, array{channel: string, envelope: array}> */
        public array $calls = [];

        public function broadcast(string $channel, array $envelope): void
        {
            $this->calls[] = ['channel' => $channel, 'envelope' => $envelope];
        }
    };

    $observer = new AgentBroadcastObserver($transport, 'session-1');

    $observer->onAgentExecutionStarted(new AgentExecutionStarted(
        agentId: 'agent-1',
        executionId: 'exec-1',
        parentAgentId: null,
        messageCount: 1,
        availableTools: 0,
    ));

    $observer->onInferenceRequestStarted(new InferenceRequestStarted(
        agentId: 'agent-1',
        executionId: 'exec-1',
        parentAgentId: null,
        stepNumber: 1,
        messageCount: 1,
        model: null,
        inferenceExecutionId: 'inference-1',
    ));

    $observer->onAgentExecutionCompleted(new AgentExecutionCompleted(
        agentId: 'agent-1',
        executionId: 'exec-1',
        parentAgentId: null,
        status: ExecutionStatus::Completed,
        totalSteps: 1,
        totalUsage: InferenceUsage::none(),
        errors: null,
    ));

    $observer->onPartialInferenceDelta(new PartialInferenceDeltaCreated([
        'executionId' => 'inference-1',
        'contentDelta' => 'ignored',
    ]));

    expect($transport->calls)->toBe([]);
});

it('emits failed status when execution fails', function () {
    $transport = new class implements CanBroadcastAgentEvents {
        /** @var array<int, array{channel: string, envelope: array}> */
        public array $calls = [];

        public function broadcast(string $channel, array $envelope): void
        {
            $this->calls[] = ['channel' => $channel, 'envelope' => $envelope];
        }
    };

    $observer = new AgentBroadcastObserver($transport, 'session-1');

    $observer->onAgentExecutionStarted(new AgentExecutionStarted(
        agentId: 'agent-1',
        executionId: 'exec-1',
        parentAgentId: null,
        messageCount: 1,
        availableTools: 0,
    ));

    $observer->onAgentExecutionFailed(new AgentExecutionFailed(
        agentId: 'agent-1',
        executionId: 'exec-1',
        parentAgentId: null,
        exception: new \RuntimeException('boom'),
        status: ExecutionStatus::Failed,
        stepsCompleted: 1,
        totalUsage: InferenceUsage::none(),
        errors: null,
    ));

    expect($transport->calls)->toHaveCount(1);
    expect($transport->calls[0]['envelope']['type'])->toBe('agent.status');
    expect($transport->calls[0]['envelope']['payload']['status'])->toBe('failed');
    expect($transport->calls[0]['envelope']['payload']['error_message'])->toBe('boom');
});
