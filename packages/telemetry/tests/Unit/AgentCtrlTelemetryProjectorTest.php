<?php declare(strict_types=1);

use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\AgentCtrl\Event\AgentErrorOccurred;
use Cognesy\AgentCtrl\Event\AgentExecutionCompleted;
use Cognesy\AgentCtrl\Event\AgentExecutionStarted;
use Cognesy\AgentCtrl\Event\RequestBuilt;
use Cognesy\AgentCtrl\Event\ResponseParsingCompleted;
use Cognesy\AgentCtrl\Telemetry\AgentCtrlTelemetryProjector;
use Cognesy\AgentCtrl\ValueObject\AgentCtrlExecutionId;
use Cognesy\Telemetry\Adapters\OTel\OtelExporter;
use Cognesy\Telemetry\Application\Registry\TraceRegistry;
use Cognesy\Telemetry\Application\Telemetry;
use Cognesy\Telemetry\Domain\Observation\ObservationKind;
use Cognesy\Telemetry\Domain\Observation\ObservationStatus;

it('correlates agent-ctrl success events under one execution root', function () {
    $exporter = new OtelExporter();
    $projector = new AgentCtrlTelemetryProjector(new Telemetry(new TraceRegistry(), $exporter));
    $executionId = AgentCtrlExecutionId::fromString('exec-success');

    $projector->project(new AgentExecutionStarted(AgentType::Codex, $executionId, 'Inspect repo', 'gpt-codex', '/tmp/work'));
    $projector->project(new RequestBuilt(AgentType::Codex, $executionId, 'CodexRequest', 2.5));
    $projector->project(new ResponseParsingCompleted(AgentType::Codex, $executionId, 4.5, 'session-123'));
    $projector->project(new AgentExecutionCompleted(AgentType::Codex, $executionId, 0, 1, 0.25, 10, 20));

    $observations = $exporter->observations();

    expect($observations)->toHaveCount(3);
    expect($observations[0]->kind())->toBe(ObservationKind::Log)
        ->and($observations[0]->name())->toBe('agent_ctrl.RequestBuilt')
        ->and($observations[0]->attributes()->toArray()['agent_ctrl.execution_id'] ?? null)->toBe('exec-success');
    expect($observations[1]->kind())->toBe(ObservationKind::Log)
        ->and($observations[1]->name())->toBe('agent_ctrl.ResponseParsingCompleted')
        ->and($observations[1]->attributes()->toArray()['agent_ctrl.session_id'] ?? null)->toBe('session-123');
    expect($observations[2]->kind())->toBe(ObservationKind::Span)
        ->and($observations[2]->name())->toBe('agent_ctrl.execute')
        ->and($observations[2]->status())->toBe(ObservationStatus::Ok)
        ->and($observations[2]->attributes()->toArray()['agent_ctrl.execution_id'] ?? null)->toBe('exec-success');
    expect($observations[0]->spanReference()->traceId()->value())->toBe($observations[2]->spanReference()->traceId()->value())
        ->and($observations[0]->spanReference()->parentSpanId()?->value())->toBe($observations[2]->spanReference()->spanId()->value());
});

it('fails the execution root when agent-ctrl reports an error', function () {
    $exporter = new OtelExporter();
    $projector = new AgentCtrlTelemetryProjector(new Telemetry(new TraceRegistry(), $exporter));
    $executionId = AgentCtrlExecutionId::fromString('exec-error');

    $projector->project(new AgentExecutionStarted(AgentType::OpenCode, $executionId, 'Fix bug', 'gpt-opencode', '/tmp/work'));
    $projector->project(new AgentErrorOccurred(AgentType::OpenCode, $executionId, 'boom', RuntimeException::class, 1));

    $observations = $exporter->observations();

    expect($observations)->toHaveCount(1);
    expect($observations[0]->kind())->toBe(ObservationKind::Span)
        ->and($observations[0]->name())->toBe('agent_ctrl.execute')
        ->and($observations[0]->status())->toBe(ObservationStatus::Error)
        ->and($observations[0]->attributes()->toArray()['agent_ctrl.error'] ?? null)->toBe('boom');
});

it('keeps separate execution roots when two runs share the same session id', function () {
    $exporter = new OtelExporter();
    $projector = new AgentCtrlTelemetryProjector(new Telemetry(new TraceRegistry(), $exporter));
    $firstExecutionId = AgentCtrlExecutionId::fromString('exec-one');
    $secondExecutionId = AgentCtrlExecutionId::fromString('exec-two');

    $projector->project(new AgentExecutionStarted(AgentType::Codex, $firstExecutionId, 'Inspect repo', 'gpt-codex', '/tmp/work'));
    $projector->project(new ResponseParsingCompleted(AgentType::Codex, $firstExecutionId, 4.5, 'session-shared'));
    $projector->project(new AgentExecutionCompleted(AgentType::Codex, $firstExecutionId, 0, 0));

    $projector->project(new AgentExecutionStarted(AgentType::Codex, $secondExecutionId, 'Apply change', 'gpt-codex', '/tmp/work'));
    $projector->project(new ResponseParsingCompleted(AgentType::Codex, $secondExecutionId, 5.5, 'session-shared'));
    $projector->project(new AgentExecutionCompleted(AgentType::Codex, $secondExecutionId, 0, 0));

    $observations = $exporter->observations();
    $logs = array_values(array_filter(
        $observations,
        static fn($observation): bool => $observation->kind() === ObservationKind::Log,
    ));
    $spans = array_values(array_filter(
        $observations,
        static fn($observation): bool => $observation->kind() === ObservationKind::Span,
    ));

    expect($logs)->toHaveCount(2);
    expect($logs[0]->attributes()->toArray()['agent_ctrl.execution_id'] ?? null)->toBe('exec-one')
        ->and($logs[0]->attributes()->toArray()['agent_ctrl.session_id'] ?? null)->toBe('session-shared')
        ->and($logs[1]->attributes()->toArray()['agent_ctrl.execution_id'] ?? null)->toBe('exec-two')
        ->and($logs[1]->attributes()->toArray()['agent_ctrl.session_id'] ?? null)->toBe('session-shared');
    expect($spans)->toHaveCount(2);
    expect($spans[0]->attributes()->toArray()['agent_ctrl.execution_id'] ?? null)->toBe('exec-one')
        ->and($spans[1]->attributes()->toArray()['agent_ctrl.execution_id'] ?? null)->toBe('exec-two')
        ->and($spans[0]->spanReference()->traceId()->value())->not->toBe($spans[1]->spanReference()->traceId()->value());
});
