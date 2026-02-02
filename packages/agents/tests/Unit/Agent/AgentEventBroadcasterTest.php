<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\AgentHooks\Guards\StepsLimitHook;
use Cognesy\Agents\AgentHooks\Hooks\ErrorPolicyHook;
use Cognesy\Agents\Broadcasting\AgentEventBroadcaster;
use Cognesy\Agents\Broadcasting\BroadcastConfig;
use Cognesy\Agents\Broadcasting\CanBroadcastAgentEvents;
use Cognesy\Agents\Core\Stop\StopReason;
use Cognesy\Agents\Core\Stop\StopSignal;
use Cognesy\Agents\Events\AgentStepCompleted;
use Cognesy\Agents\Events\AgentStepStarted;
use Cognesy\Agents\Events\ContinuationEvaluated;
use Cognesy\Agents\Events\ToolCallCompleted;
use Cognesy\Agents\Events\ToolCallStarted;
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
    $eventBroadcaster = new AgentEventBroadcaster($broadcaster, 'session-1', 'exec-1');

    $eventBroadcaster->onAgentStepStarted(new AgentStepStarted(
        agentId: 'agent-1',
        parentAgentId: null,
        stepNumber: 1,
        messageCount: 2,
        availableTools: 3,
    ));

    $eventBroadcaster->onAgentStepCompleted(new AgentStepCompleted(
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
})->skip('hooks not integrated yet');

it('emits tool call events', function () {
    $broadcaster = new FakeBroadcaster();
    $eventBroadcaster = new AgentEventBroadcaster($broadcaster, 'session-1', 'exec-1');

    $eventBroadcaster->onToolCallStarted(new ToolCallStarted(
        tool: 'search',
        args: ['q' => 'hi'],
        startedAt: new DateTimeImmutable(),
    ));

    $startedAt = new DateTimeImmutable('2026-01-01 00:00:00');
    $completedAt = $startedAt->modify('+2 seconds');
    $eventBroadcaster->onToolCallCompleted(new ToolCallCompleted(
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
})->skip('hooks not integrated yet');

it('emits continuation events when enabled', function () {
    $broadcaster = new FakeBroadcaster();
    $eventBroadcaster = new AgentEventBroadcaster(
        $broadcaster,
        'session-1',
        'exec-1',
        new BroadcastConfig(includeContinuationTrace: true, autoStatusTracking: false),
    );

    $stopSignal = new StopSignal(
        reason: StopReason::StepsLimitReached,
        message: 'limit reached',
        source: StepsLimitHook::class,
    );

    $eventBroadcaster->onContinuationEvaluated(new ContinuationEvaluated(
        agentId: 'agent-1',
        parentAgentId: null,
        stepNumber: 1,
        stopSignal: $stopSignal,
    ));

    $payload = $broadcaster->calls[0]['envelope']['payload'];
    expect($broadcaster->calls[0]['envelope']['type'])->toBe('agent.continuation');
    expect($payload['should_continue'])->toBeFalse();
})->skip('hooks not integrated yet');

it('returns wiretap callable that handles all events', function () {
    $broadcaster = new FakeBroadcaster();
    $eventBroadcaster = new AgentEventBroadcaster($broadcaster, 'session-1', 'exec-1');

    $wiretap = $eventBroadcaster->wiretap();
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
})->skip('hooks not integrated yet');

it('handles StreamEventReceived for real-time chat', function () {
    $broadcaster = new FakeBroadcaster();
    $eventBroadcaster = new AgentEventBroadcaster($broadcaster, 'session-1', 'exec-1');

    $eventBroadcaster->onStreamChunk(new StreamEventReceived('Hello'));
    $eventBroadcaster->onStreamChunk(new StreamEventReceived(' world'));
    $eventBroadcaster->onStreamChunk(new StreamEventReceived('!'));

    expect(count($broadcaster->calls))->toBe(3);
    expect($broadcaster->calls[0]['envelope']['type'])->toBe('agent.stream.chunk');
    expect($broadcaster->calls[0]['envelope']['payload']['content'])->toBe('Hello');
    expect($broadcaster->calls[0]['envelope']['payload']['chunk_index'])->toBe(0);

    expect($broadcaster->calls[1]['envelope']['payload']['content'])->toBe(' world');
    expect($broadcaster->calls[1]['envelope']['payload']['chunk_index'])->toBe(1);

    expect($broadcaster->calls[2]['envelope']['payload']['content'])->toBe('!');
    expect($broadcaster->calls[2]['envelope']['payload']['chunk_index'])->toBe(2);
})->skip('hooks not integrated yet');

it('filters empty stream content', function () {
    $broadcaster = new FakeBroadcaster();
    $eventBroadcaster = new AgentEventBroadcaster($broadcaster, 'session-1', 'exec-1');

    $eventBroadcaster->onStreamChunk(new StreamEventReceived(''));
    $eventBroadcaster->onStreamChunk(new StreamEventReceived('Hello'));
    $eventBroadcaster->onStreamChunk(new StreamEventReceived(''));

    expect(count($broadcaster->calls))->toBe(1);
    expect($broadcaster->calls[0]['envelope']['payload']['content'])->toBe('Hello');
})->skip('hooks not integrated yet');

it('auto-transitions status on lifecycle events', function () {
    $broadcaster = new FakeBroadcaster();
    $eventBroadcaster = new AgentEventBroadcaster($broadcaster, 'session-1', 'exec-1');

    // Start step -> status becomes 'processing'
    $eventBroadcaster->onAgentStepStarted(new AgentStepStarted(
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
    $stopSignal = new StopSignal(
        reason: StopReason::Completed,
        message: 'No more work to do',
        source: StepsLimitHook::class,
    );

    $eventBroadcaster->onContinuationEvaluated(new ContinuationEvaluated(
        agentId: 'agent-1',
        parentAgentId: null,
        stepNumber: 1,
        stopSignal: $stopSignal,
    ));

    // Find the status event
    $statusEvents = array_filter($broadcaster->calls, fn($call) =>
        $call['envelope']['type'] === 'agent.status'
    );
    $lastStatus = array_pop($statusEvents);

    expect($lastStatus['envelope']['payload']['status'])->toBe('completed');
    expect($lastStatus['envelope']['payload']['previous_status'])->toBe('processing');
})->skip('hooks not integrated yet');

it('maps StopReason to correct status', function () {
    $broadcaster = new FakeBroadcaster();
    $eventBroadcaster = new AgentEventBroadcaster($broadcaster, 'session-1', 'exec-1');

    // Start step first to get 'processing' status
    $eventBroadcaster->onAgentStepStarted(new AgentStepStarted(
        agentId: 'agent-1',
        parentAgentId: null,
        stepNumber: 1,
        messageCount: 0,
        availableTools: 0,
    ));

    // ErrorForbade -> 'failed'
    $stopSignal = new StopSignal(
        reason: StopReason::ErrorForbade,
        message: 'Error policy forbade continuation',
        source: ErrorPolicyHook::class,
    );

    $eventBroadcaster->onContinuationEvaluated(new ContinuationEvaluated(
        agentId: 'agent-1',
        parentAgentId: null,
        stepNumber: 1,
        stopSignal: $stopSignal,
    ));

    $statusEvents = array_filter($broadcaster->calls, fn($call) =>
        $call['envelope']['type'] === 'agent.status'
    );
    $lastStatus = array_pop($statusEvents);

    expect($lastStatus['envelope']['payload']['status'])->toBe('failed');
})->skip('hooks not integrated yet');

it('does not duplicate status when already in same state', function () {
    $broadcaster = new FakeBroadcaster();
    $eventBroadcaster = new AgentEventBroadcaster($broadcaster, 'session-1', 'exec-1');

    // Two step starts should only emit 'processing' once
    $eventBroadcaster->onAgentStepStarted(new AgentStepStarted(
        agentId: 'agent-1',
        parentAgentId: null,
        stepNumber: 1,
        messageCount: 0,
        availableTools: 0,
    ));

    $eventBroadcaster->onAgentStepStarted(new AgentStepStarted(
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
})->skip('hooks not integrated yet');

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
})->skip('hooks not integrated yet');

it('respects minimal config - no streaming', function () {
    $broadcaster = new FakeBroadcaster();
    $eventBroadcaster = new AgentEventBroadcaster(
        $broadcaster,
        'session-1',
        'exec-1',
        BroadcastConfig::minimal(),
    );

    $eventBroadcaster->onStreamChunk(new StreamEventReceived('Hello'));

    expect(count($broadcaster->calls))->toBe(0);
})->skip('hooks not integrated yet');

it('includes full tool args in debug mode', function () {
    $broadcaster = new FakeBroadcaster();
    $eventBroadcaster = new AgentEventBroadcaster(
        $broadcaster,
        'session-1',
        'exec-1',
        BroadcastConfig::debug(),
    );

    $eventBroadcaster->onToolCallStarted(new ToolCallStarted(
        tool: 'search',
        args: ['query' => 'test query', 'limit' => 10],
        startedAt: new DateTimeImmutable(),
    ));

    expect($broadcaster->calls[0]['envelope']['payload'])->toHaveKey('args');
    expect($broadcaster->calls[0]['envelope']['payload']['args'])->toBe([
        'query' => 'test query',
        'limit' => 10,
    ]);
})->skip('hooks not integrated yet');

it('can be reset for new executions', function () {
    $broadcaster = new FakeBroadcaster();
    $eventBroadcaster = new AgentEventBroadcaster($broadcaster, 'session-1', 'exec-1');

    // First execution
    $eventBroadcaster->onStreamChunk(new StreamEventReceived('Hello'));
    expect($broadcaster->calls[0]['envelope']['payload']['chunk_index'])->toBe(0);

    // Reset
    $eventBroadcaster->reset();

    // Second execution
    $eventBroadcaster->onStreamChunk(new StreamEventReceived('World'));
    expect($broadcaster->calls[1]['envelope']['payload']['chunk_index'])->toBe(0);
})->skip('hooks not integrated yet');
