<?php declare(strict_types=1);

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Capability\Subagent\UseSubagents;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\Telemetry\AgentsTelemetryProjector;
use Cognesy\Agents\Telemetry\AgentStateTelemetry;
use Cognesy\Agents\Template\AgentDefinitionRegistry;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Telemetry\Adapters\OTel\OtelExporter;
use Cognesy\Telemetry\Application\Projector\RuntimeEventBridge;
use Cognesy\Telemetry\Application\Registry\TraceRegistry;
use Cognesy\Telemetry\Application\Telemetry;
use Cognesy\Telemetry\Domain\Continuation\TelemetryContinuation;
use Cognesy\Telemetry\Domain\Trace\TraceContext;

it('projects subagent execution and lifecycle under the parent tool-call span', function () {
    $otel = new OtelExporter();
    $hub = new Telemetry(new TraceRegistry(), $otel);
    $events = new EventDispatcher('telemetry.subagent.projector.test');
    (new RuntimeEventBridge(new AgentsTelemetryProjector($hub)))->attachTo($events);

    $registry = new AgentDefinitionRegistry();
    $registry->register(new AgentDefinition(
        name: 'reviewer',
        description: 'Reviews code',
        systemPrompt: 'You are a reviewer',
        llmConfig: '',
    ));

    $driver = (new FakeAgentDriver([
        ScenarioStep::toolCall('spawn_subagent', [
            'subagent' => 'reviewer',
            'prompt' => 'Review this code',
        ], executeTools: true),
        ScenarioStep::final('parent done'),
    ]))->withChildSteps([
        ScenarioStep::final('child done'),
    ]);

    $agent = AgentBuilder::base($events)
        ->withCapability(new UseDriver($driver))
        ->withCapability(new UseSubagents(provider: $registry))
        ->build();

    $state = AgentStateTelemetry::storeContinuation(
        AgentState::empty(),
        new TelemetryContinuation(TraceContext::fresh(), ['session_id' => 'session-123']),
    );

    $agent->execute($state);

    $observations = $otel->observations();
    $agentSpans = array_values(array_filter(
        $observations,
        static fn($observation): bool => $observation->name() === 'agent.execute',
    ));
    $toolSpans = array_values(array_filter(
        $observations,
        static fn($observation): bool => $observation->name() === 'agent.tool_call',
    ));
    $spawnLogs = array_values(array_filter(
        $observations,
        static fn($observation): bool => $observation->name() === 'agent.subagent.spawning',
    ));
    $completedLogs = array_values(array_filter(
        $observations,
        static fn($observation): bool => $observation->name() === 'agent.subagent.completed',
    ));

    expect($agentSpans)->toHaveCount(2);
    expect($toolSpans)->toHaveCount(1);
    expect($spawnLogs)->toHaveCount(1);
    expect($completedLogs)->toHaveCount(1);

    [$firstAgentSpan, $secondAgentSpan] = $agentSpans;
    $parentExecution = ($firstAgentSpan->attributes()->toArray()['agent.parent_id'] ?? null) === null ? $firstAgentSpan : $secondAgentSpan;
    $childExecution = $parentExecution === $firstAgentSpan ? $secondAgentSpan : $firstAgentSpan;
    $toolSpan = $toolSpans[0];

    expect($childExecution->spanReference()->traceId()->value())->toBe($parentExecution->spanReference()->traceId()->value());
    expect($childExecution->spanReference()->parentSpanId()?->value())->toBe($toolSpan->spanReference()->spanId()->value());
    expect($spawnLogs[0]->spanReference()->parentSpanId()?->value())->toBe($toolSpan->spanReference()->spanId()->value());
    expect($completedLogs[0]->spanReference()->parentSpanId()?->value())->toBe($toolSpan->spanReference()->spanId()->value());
    expect($childExecution->attributes()->toArray()['telemetry.session_id'] ?? null)->toBe('session-123');
});
