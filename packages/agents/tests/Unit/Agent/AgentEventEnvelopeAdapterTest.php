<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Broadcasting\AgentEventEnvelopeAdapter;
use Cognesy\Agents\Broadcasting\BroadcastConfig;
use Cognesy\Agents\Broadcasting\CanBroadcastAgentEvents;
use Cognesy\Agents\Core\Continuation\Criteria\ErrorPolicyCriterion;
use Cognesy\Agents\Core\Continuation\Criteria\StepsLimit;
use Cognesy\Agents\Core\Continuation\Data\ContinuationEvaluation;
use Cognesy\Agents\Core\Continuation\Data\ContinuationOutcome;
use Cognesy\Agents\Core\Continuation\Enums\ContinuationDecision;
use Cognesy\Agents\Core\Continuation\Enums\StopReason;
use Cognesy\Agents\Core\Events\AgentStepCompleted;
use Cognesy\Agents\Core\Events\AgentStepStarted;
use Cognesy\Agents\Core\Events\ContinuationEvaluated;
use Cognesy\Agents\Core\Events\ToolCallCompleted;
use Cognesy\Agents\Core\Events\ToolCallStarted;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Events\StreamEventReceived;
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
    $adapter = new AgentEventEnvelopeAdapter($broadcaster, 'session-1', 'exec-1');

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

    // First call is status 'processing', second is step.started
    expect($broadcaster->calls[0]['envelope']['type'])->toBe('agent.status');
    expect($broadcaster->calls[0]['envelope']['payload']['status'])->toBe('processing');

    expect($broadcaster->calls[1]['envelope']['type'])->toBe('agent.step.started');
    expect($broadcaster->calls[1]['envelope']['payload']['step_number'])->toBe(1);

    expect($broadcaster->calls[2]['envelope']['type'])->toBe('agent.step.completed');
    expect($broadcaster->calls[2]['envelope']['payload']['errors'])->toBe(0);
});

it('emits tool call events', function () {
    $broadcaster = new FakeBroadcaster();
    $adapter = new AgentEventEnvelopeAdapter($broadcaster, 'session-1', 'exec-1');

    $adapter->onToolCallStarted(new ToolCallStarted(
        tool: 'search',
        args: ['q' => 'hi'],
        startedAt: new DateTimeImmutable(),
    ));

    $startedAt = new DateTimeImmutable('2026-01-01 00:00:00');
    $completedAt = $startedAt->modify('+2 seconds');
    $adapter->onToolCallCompleted(new ToolCallCompleted(
        tool: 'search',
        success: true,
        error: null,
        startedAt: $startedAt,
        completedAt: $completedAt,
    ));

    expect($broadcaster->calls[0]['envelope']['type'])->toBe('agent.tool.started');
    expect($broadcaster->calls[0]['envelope']['payload']['tool_name'])->toBe('search');
    expect($broadcaster->calls[0]['envelope']['payload']['args_summary'])->toBe("q: 'hi'");

    expect($broadcaster->calls[1]['envelope']['type'])->toBe('agent.tool.completed');
    expect($broadcaster->calls[1]['envelope']['payload']['duration_ms'])->toBe(2000);
});

it('emits continuation events when enabled', function () {
    $broadcaster = new FakeBroadcaster();
    $adapter = new AgentEventEnvelopeAdapter(
        $broadcaster,
        'session-1',
        'exec-1',
        new BroadcastConfig(includeContinuationTrace: true, autoStatusTracking: false),
    );

    $evaluation = new ContinuationEvaluation(
        criterionClass: StepsLimit::class,
        decision: ContinuationDecision::ForbidContinuation,
        reason: 'limit reached',
        stopReason: StopReason::StepsLimitReached,
    );
    $outcome = ContinuationOutcome::fromEvaluations([$evaluation]);

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

it('returns wiretap callable that handles all events', function () {
    $broadcaster = new FakeBroadcaster();
    $adapter = new AgentEventEnvelopeAdapter($broadcaster, 'session-1', 'exec-1');

    $wiretap = $adapter->wiretap();
    expect($wiretap)->toBeCallable();

    // Send events through wiretap
    $wiretap(new AgentStepStarted(
        agentId: 'agent-1',
        parentAgentId: null,
        stepNumber: 1,
        messageCount: 2,
        availableTools: 3,
    ));

    $wiretap(new ToolCallStarted(
        tool: 'search',
        args: ['q' => 'test'],
        startedAt: new DateTimeImmutable(),
    ));

    // Verify events were broadcast (status + step.started + tool.started)
    expect(count($broadcaster->calls))->toBe(3);
    expect($broadcaster->calls[0]['envelope']['type'])->toBe('agent.status');
    expect($broadcaster->calls[1]['envelope']['type'])->toBe('agent.step.started');
    expect($broadcaster->calls[2]['envelope']['type'])->toBe('agent.tool.started');
});

it('handles StreamEventReceived for real-time chat', function () {
    $broadcaster = new FakeBroadcaster();
    $adapter = new AgentEventEnvelopeAdapter($broadcaster, 'session-1', 'exec-1');

    $adapter->onStreamChunk(new StreamEventReceived('Hello'));
    $adapter->onStreamChunk(new StreamEventReceived(' world'));
    $adapter->onStreamChunk(new StreamEventReceived('!'));

    expect(count($broadcaster->calls))->toBe(3);
    expect($broadcaster->calls[0]['envelope']['type'])->toBe('agent.stream.chunk');
    expect($broadcaster->calls[0]['envelope']['payload']['content'])->toBe('Hello');
    expect($broadcaster->calls[0]['envelope']['payload']['chunk_index'])->toBe(0);

    expect($broadcaster->calls[1]['envelope']['payload']['content'])->toBe(' world');
    expect($broadcaster->calls[1]['envelope']['payload']['chunk_index'])->toBe(1);

    expect($broadcaster->calls[2]['envelope']['payload']['content'])->toBe('!');
    expect($broadcaster->calls[2]['envelope']['payload']['chunk_index'])->toBe(2);
});

it('filters empty stream content', function () {
    $broadcaster = new FakeBroadcaster();
    $adapter = new AgentEventEnvelopeAdapter($broadcaster, 'session-1', 'exec-1');

    $adapter->onStreamChunk(new StreamEventReceived(''));
    $adapter->onStreamChunk(new StreamEventReceived('Hello'));
    $adapter->onStreamChunk(new StreamEventReceived(''));

    expect(count($broadcaster->calls))->toBe(1);
    expect($broadcaster->calls[0]['envelope']['payload']['content'])->toBe('Hello');
});

it('auto-transitions status on lifecycle events', function () {
    $broadcaster = new FakeBroadcaster();
    $adapter = new AgentEventEnvelopeAdapter($broadcaster, 'session-1', 'exec-1');

    // Start step -> status becomes 'processing'
    $adapter->onAgentStepStarted(new AgentStepStarted(
        agentId: 'agent-1',
        parentAgentId: null,
        stepNumber: 1,
        messageCount: 0,
        availableTools: 0,
    ));

    expect($broadcaster->calls[0]['envelope']['type'])->toBe('agent.status');
    expect($broadcaster->calls[0]['envelope']['payload']['status'])->toBe('processing');
    expect($broadcaster->calls[0]['envelope']['payload']['previous_status'])->toBe('idle');

    // Complete with Completed stop reason -> status becomes 'completed'
    // Use AllowStop evaluation to signal no more work to do
    $evaluation = new ContinuationEvaluation(
        criterionClass: StepsLimit::class,
        decision: ContinuationDecision::AllowStop,
        reason: 'No more work to do',
    );
    $outcome = ContinuationOutcome::fromEvaluations([$evaluation]);

    $adapter->onContinuationEvaluated(new ContinuationEvaluated(
        agentId: 'agent-1',
        parentAgentId: null,
        stepNumber: 1,
        outcome: $outcome,
    ));

    // Find the status event
    $statusEvents = array_filter($broadcaster->calls, fn($call) =>
        $call['envelope']['type'] === 'agent.status'
    );
    $lastStatus = array_pop($statusEvents);

    expect($lastStatus['envelope']['payload']['status'])->toBe('completed');
    expect($lastStatus['envelope']['payload']['previous_status'])->toBe('processing');
});

it('maps StopReason to correct status', function () {
    $broadcaster = new FakeBroadcaster();
    $adapter = new AgentEventEnvelopeAdapter($broadcaster, 'session-1', 'exec-1');

    // Start step first to get 'processing' status
    $adapter->onAgentStepStarted(new AgentStepStarted(
        agentId: 'agent-1',
        parentAgentId: null,
        stepNumber: 1,
        messageCount: 0,
        availableTools: 0,
    ));

    // ErrorForbade -> 'failed'
    $evaluation = new ContinuationEvaluation(
        criterionClass: ErrorPolicyCriterion::class,
        decision: ContinuationDecision::ForbidContinuation,
        reason: 'Error policy forbade continuation',
        stopReason: StopReason::ErrorForbade,
    );
    $outcome = ContinuationOutcome::fromEvaluations([$evaluation]);

    $adapter->onContinuationEvaluated(new ContinuationEvaluated(
        agentId: 'agent-1',
        parentAgentId: null,
        stepNumber: 1,
        outcome: $outcome,
    ));

    $statusEvents = array_filter($broadcaster->calls, fn($call) =>
        $call['envelope']['type'] === 'agent.status'
    );
    $lastStatus = array_pop($statusEvents);

    expect($lastStatus['envelope']['payload']['status'])->toBe('failed');
});

it('does not duplicate status when already in same state', function () {
    $broadcaster = new FakeBroadcaster();
    $adapter = new AgentEventEnvelopeAdapter($broadcaster, 'session-1', 'exec-1');

    // Two step starts should only emit 'processing' once
    $adapter->onAgentStepStarted(new AgentStepStarted(
        agentId: 'agent-1',
        parentAgentId: null,
        stepNumber: 1,
        messageCount: 0,
        availableTools: 0,
    ));

    $adapter->onAgentStepStarted(new AgentStepStarted(
        agentId: 'agent-1',
        parentAgentId: null,
        stepNumber: 2,
        messageCount: 0,
        availableTools: 0,
    ));

    $statusEvents = array_filter($broadcaster->calls, fn($call) =>
        $call['envelope']['type'] === 'agent.status'
    );

    expect(count($statusEvents))->toBe(1);
});

it('supports config presets', function () {
    // Minimal config - no streaming
    $minimalConfig = BroadcastConfig::minimal();
    expect($minimalConfig->includeStreamChunks)->toBeFalse();
    expect($minimalConfig->includeContinuationTrace)->toBeFalse();
    expect($minimalConfig->autoStatusTracking)->toBeTrue();

    // Standard config - streaming enabled
    $standardConfig = BroadcastConfig::standard();
    expect($standardConfig->includeStreamChunks)->toBeTrue();
    expect($standardConfig->includeContinuationTrace)->toBeFalse();

    // Debug config - everything enabled
    $debugConfig = BroadcastConfig::debug();
    expect($debugConfig->includeStreamChunks)->toBeTrue();
    expect($debugConfig->includeContinuationTrace)->toBeTrue();
    expect($debugConfig->includeToolArgs)->toBeTrue();
    expect($debugConfig->maxArgLength)->toBe(500);
});

it('respects minimal config - no streaming', function () {
    $broadcaster = new FakeBroadcaster();
    $adapter = new AgentEventEnvelopeAdapter(
        $broadcaster,
        'session-1',
        'exec-1',
        BroadcastConfig::minimal(),
    );

    $adapter->onStreamChunk(new StreamEventReceived('Hello'));

    expect(count($broadcaster->calls))->toBe(0);
});

it('includes full tool args in debug mode', function () {
    $broadcaster = new FakeBroadcaster();
    $adapter = new AgentEventEnvelopeAdapter(
        $broadcaster,
        'session-1',
        'exec-1',
        BroadcastConfig::debug(),
    );

    $adapter->onToolCallStarted(new ToolCallStarted(
        tool: 'search',
        args: ['query' => 'test query', 'limit' => 10],
        startedAt: new DateTimeImmutable(),
    ));

    expect($broadcaster->calls[0]['envelope']['payload'])->toHaveKey('args');
    expect($broadcaster->calls[0]['envelope']['payload']['args'])->toBe([
        'query' => 'test query',
        'limit' => 10,
    ]);
});

it('can be reset for new executions', function () {
    $broadcaster = new FakeBroadcaster();
    $adapter = new AgentEventEnvelopeAdapter($broadcaster, 'session-1', 'exec-1');

    // First execution
    $adapter->onStreamChunk(new StreamEventReceived('Hello'));
    expect($broadcaster->calls[0]['envelope']['payload']['chunk_index'])->toBe(0);

    // Reset
    $adapter->reset();

    // Second execution
    $adapter->onStreamChunk(new StreamEventReceived('World'));
    expect($broadcaster->calls[1]['envelope']['payload']['chunk_index'])->toBe(0);
});
