<?php declare(strict_types=1);

use Cognesy\Metrics\Data\Counter;
use Cognesy\Metrics\Data\Histogram;
use Cognesy\Metrics\Data\Tags;
use Cognesy\Telemetry\Adapters\OTel\OtelExporter;
use Cognesy\Telemetry\Application\Exporter\CompositeTelemetryExporter;
use Cognesy\Telemetry\Application\Registry\TraceRegistry;
use Cognesy\Telemetry\Application\Telemetry;
use Cognesy\Telemetry\Domain\Observation\ObservationStatus;
use Cognesy\Telemetry\Domain\Value\AttributeBag;

it('exports root spans child spans logs and metrics', function () {
    $otel = new OtelExporter();
    $composite = new CompositeTelemetryExporter([$otel]);
    $telemetry = new Telemetry(
        registry: new TraceRegistry(),
        exporter: $composite,
    );

    $root = $telemetry->openRoot(
        key: 'root',
        name: 'agent.execute',
        attributes: AttributeBag::empty()->with('agent.id', 'a1'),
    );
    $child = $telemetry->openChild('child', 'root', 'agent.step');
    $telemetry->log('child', 'tool.called');
    $telemetry->complete('child', AttributeBag::empty()->with('step.number', 1));
    $telemetry->complete('root');
    $telemetry->metric(Histogram::create('inference.client.token.usage', 42));

    expect($root->spanId()->value())->not->toBe($child->spanId()->value());
    expect($otel->observations())->toHaveCount(3);
    expect($otel->observations()[0]->spanReference()->parentSpanId()?->value())
        ->toBe($child->spanId()->value());
    expect($otel->observations()[1]->spanReference()->parentSpanId()?->value())
        ->toBe($root->spanId()->value());
    expect($otel->observations()[2]->spanReference()->spanId()->value())
        ->toBe($root->spanId()->value());
    expect($telemetry->traceContext('root'))->toBeNull();
});

it('flushes pending metrics to exporter on flush', function () {
    $otel = new OtelExporter();
    $composite = new CompositeTelemetryExporter([$otel]);
    $telemetry = new Telemetry(
        registry: new TraceRegistry(),
        exporter: $composite,
    );

    $telemetry->openRoot('root', 'agent.execute');
    $telemetry->metric(Counter::create('agent.executions', 1));
    $telemetry->metric(Histogram::create('inference.client.token.usage.total', 100));
    $telemetry->complete('root');

    // metrics are pending — not yet sent
    // flush sends observations and routes metrics to CanExportMetrics exporters
    $telemetry->flush();

    expect($otel->observations())->toHaveCount(1);
});

it('drops log observations when the parent span is missing', function () {
    $otel = new OtelExporter();
    $telemetry = new Telemetry(
        registry: new TraceRegistry(),
        exporter: new CompositeTelemetryExporter([$otel]),
    );

    $observation = $telemetry->log('missing', 'tool.called');

    expect($observation)->toBeNull();
    expect($otel->observations())->toBe([]);
});

it('exports error log observations when requested', function () {
    $otel = new OtelExporter();
    $telemetry = new Telemetry(
        registry: new TraceRegistry(),
        exporter: new CompositeTelemetryExporter([$otel]),
    );

    $root = $telemetry->openRoot('root', 'agent.execute');
    $telemetry->log(
        key: 'root',
        name: 'agent.failure',
        attributes: AttributeBag::empty()->with('error.message', 'boom'),
        status: ObservationStatus::Error,
    );

    expect($otel->observations())->toHaveCount(1);
    expect($otel->observations()[0]->status())->toBe(ObservationStatus::Error)
        ->and($otel->observations()[0]->spanReference()->parentSpanId()?->value())->toBe($root->spanId()->value());
});
