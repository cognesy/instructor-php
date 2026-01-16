<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Agent;

use Cognesy\Addons\Agent\Broadcasting\CanBroadcastAgentEvents;
use Cognesy\Addons\Agent\Broadcasting\ReverbAgentEventAdapter;
use Cognesy\Addons\Agent\Events\AgentStepCompleted;
use Cognesy\Addons\Agent\Events\AgentStepStarted;
use Cognesy\Addons\Agent\Events\ContinuationEvaluated;
use Cognesy\Addons\Agent\Events\ToolCallCompleted;
use Cognesy\Addons\Agent\Events\ToolCallStarted;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Addons\StepByStep\Continuation\ContinuationEvaluation;
use Cognesy\Addons\StepByStep\Continuation\ContinuationOutcome;
use Cognesy\Addons\StepByStep\Continuation\StopReason;
use Cognesy\Polyglot\Inference\Data\Usage;
use DateTimeImmutable;

final class FakeBroadcaster implements CanBroadcastAgentEvents
{
    /** @var array<int, array{channel: string, envelope: array}> */
    public array $calls = [];

    public function broadcast(string $channel, array $envelope): void {
        $this->calls[] = ['channel' => $channel, 'envelope' => $envelope];
    }
}

it('emits agent step events', function () {
    $broadcaster = new FakeBroadcaster();
    $adapter = new ReverbAgentEventAdapter($broadcaster, 'session-1', 'exec-1');

    $adapter->onAgentStepStarted(new AgentStepStarted(
        agentId: 'agent-1',
        parentAgentId: null,
        stepNumber: 1,
        messageCount: 2,
        availableTools: 3,
    ));

    $adapter->onAgentStepCompleted(new AgentStepCompleted(
        agentId: 'agent-1',
        parentAgentId: null,
        stepNumber: 1,
        hasToolCalls: false,
        errorCount: 0,
        errorMessages: '',
        usage: new Usage(1, 2),
        finishReason: null,
        startedAt: new DateTimeImmutable(),
    ));

    expect($broadcaster->calls[0]['envelope']['type'])->toBe('agent.step.started');
    expect($broadcaster->calls[0]['envelope']['payload']['step_number'])->toBe(1);
    expect($broadcaster->calls[1]['envelope']['type'])->toBe('agent.step.completed');
    expect($broadcaster->calls[1]['envelope']['payload']['errors'])->toBe(0);
});

it('emits tool call events', function () {
    $broadcaster = new FakeBroadcaster();
    $adapter = new ReverbAgentEventAdapter($broadcaster, 'session-1', 'exec-1');

    $adapter->onToolCallStarted(new ToolCallStarted(
        tool: 'search',
        args: ['q' => 'hi'],
        startedAt: new DateTimeImmutable(),
    ));

    $startedAt = new DateTimeImmutable('2026-01-01 00:00:00');
    $endedAt = $startedAt->modify('+2 seconds');
    $adapter->onToolCallCompleted(new ToolCallCompleted(
        tool: 'search',
        success: true,
        error: null,
        startedAt: $startedAt,
        endedAt: $endedAt,
    ));

    expect($broadcaster->calls[0]['envelope']['type'])->toBe('agent.tool.started');
    expect($broadcaster->calls[0]['envelope']['payload']['tool_name'])->toBe('search');
    expect($broadcaster->calls[0]['envelope']['payload']['args_summary'])->toBe("q: 'hi'");

    expect($broadcaster->calls[1]['envelope']['type'])->toBe('agent.tool.completed');
    expect($broadcaster->calls[1]['envelope']['payload']['duration_ms'])->toBe(2000);
});

it('emits continuation events when enabled', function () {
    $broadcaster = new FakeBroadcaster();
    $adapter = new ReverbAgentEventAdapter($broadcaster, 'session-1', 'exec-1', true);

    $evaluation = new ContinuationEvaluation(
        criterionClass: 'StepsLimit',
        decision: ContinuationDecision::ForbidContinuation,
        reason: 'limit reached',
    );
    $outcome = new ContinuationOutcome(
        decision: ContinuationDecision::AllowStop,
        shouldContinue: false,
        resolvedBy: 'StepsLimit',
        stopReason: StopReason::StepsLimitReached,
        evaluations: [$evaluation],
    );

    $adapter->onContinuationEvaluated(new ContinuationEvaluated(
        agentId: 'agent-1',
        parentAgentId: null,
        stepNumber: 1,
        outcome: $outcome,
    ));

    $payload = $broadcaster->calls[0]['envelope']['payload'];
    expect($broadcaster->calls[0]['envelope']['type'])->toBe('agent.continuation');
    expect($payload['should_continue'])->toBeFalse();
    expect($payload['evaluations'][0]['criterion'])->toBe('StepsLimit');
});
