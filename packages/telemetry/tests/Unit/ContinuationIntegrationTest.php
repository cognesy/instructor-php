<?php declare(strict_types=1);

use Cognesy\AgentCtrl\Dto\AgentResponse;
use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\AgentCtrl\Telemetry\AgentCtrlTelemetry;
use Cognesy\AgentCtrl\ValueObject\AgentCtrlExecutionId;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Telemetry\AgentStateTelemetry;
use Cognesy\Telemetry\Application\Registry\TraceRegistry;
use Cognesy\Telemetry\Application\Telemetry;
use Cognesy\Telemetry\Adapters\OTel\OtelExporter;

it('stores and loads telemetry continuation through agent state metadata', function () {
    $hub = new Telemetry(new TraceRegistry(), new OtelExporter());
    $hub->openRoot('session-run', 'agent.execute');
    $continuation = $hub->continuation('session-run', ['session_id' => 's1']);

    expect($continuation)->not->toBeNull();
    $resolvedContinuation = $continuation ?? throw new RuntimeException('Missing continuation');

    $state = AgentStateTelemetry::storeContinuation(AgentState::empty(), $resolvedContinuation);
    $loaded = AgentStateTelemetry::loadContinuation($state);

    expect($loaded?->context()->traceparent())->toBe($resolvedContinuation->context()->traceparent());
    expect($loaded?->correlation())->toBe(['session_id' => 's1']);
});

it('creates agent-ctrl continuation from response session id', function () {
    $hub = new Telemetry(new TraceRegistry(), new OtelExporter());
    $hub->openRoot('agent-ctrl-run', 'agent_ctrl.execute');
    $context = $hub->traceContext('agent-ctrl-run');

    $response = new AgentResponse(
        agentType: AgentType::Codex,
        text: 'done',
        exitCode: 0,
        executionId: AgentCtrlExecutionId::fromString('exec-123'),
        sessionId: 'session-123',
    );

    $continuation = AgentCtrlTelemetry::continuationFromResponse($response, $context ?? throw new RuntimeException('Missing context'));

    expect($continuation->correlation())->toBe([
        'agent_ctrl.execution_id' => 'exec-123',
        'agent_ctrl.session_id' => 'session-123',
    ]);
});
