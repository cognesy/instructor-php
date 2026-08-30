<?php declare(strict_types=1);

use Cognesy\Metrics\Data\Counter;
use Cognesy\Metrics\Data\Gauge;
use Cognesy\Metrics\Data\Histogram;
use Cognesy\Metrics\Data\Tags;
use Cognesy\Metrics\Data\Timer;
use Cognesy\Telemetry\Adapters\Langfuse\LangfuseExporter;
use Cognesy\Telemetry\Adapters\Logfire\LogfireExporter;
use Cognesy\Telemetry\Adapters\OTel\CanSendOtelPayloads;
use Cognesy\Telemetry\Adapters\OTel\OtelExporter;
use Cognesy\Telemetry\Application\Exporter\CompositeTelemetryExporter;
use Cognesy\Telemetry\Domain\Observation\Observation;
use Cognesy\Telemetry\Domain\Trace\SpanReference;
use Cognesy\Telemetry\Domain\Trace\TraceContext;
use Cognesy\Telemetry\Domain\Value\AttributeBag;

final class CaptureOtelTransport implements CanSendOtelPayloads
{
    /** @var array<string, array<string, mixed>> */
    public array $payloads = [];

    #[\Override]
    public function send(string $signal, array $payload): void
    {
        $this->payloads[$signal] = $payload;
    }
}

final class CaptureLangfuseTransport implements CanSendOtelPayloads
{
    /** @var array<string, array<string, mixed>> */
    public array $payloads = [];

    #[\Override]
    public function send(string $signal, array $payload): void
    {
        $this->payloads[$signal] = $payload;
    }
}

it('maps observations and counter metrics to otel payloads with correct type', function () {
    $transport = new CaptureOtelTransport();
    $exporter = new OtelExporter(transport: $transport);
    $reference = SpanReference::fromContext(TraceContext::fresh());
    $observation = Observation::span($reference, 'agent.execute', AttributeBag::empty()->with('agent.id', 'a1'));

    $exporter->exportObservation($observation);
    $exporter->export([Counter::create('agent.executions', 1)]);
    $exporter->flush();

    expect($transport->payloads)->toHaveKeys(['traces', 'metrics']);
    expect($transport->payloads['traces']['resourceSpans'][0]['scopeSpans'][0]['spans'][0]['name'])
        ->toBe('agent.execute');
    expect($transport->payloads['metrics']['resourceMetrics'][0]['scopeMetrics'][0]['metrics'][0])
        ->toHaveKey('sum');
    expect($transport->payloads['metrics']['resourceMetrics'][0]['scopeMetrics'][0]['metrics'][0]['sum']['isMonotonic'])
        ->toBeTrue();
});

it('maps histogram metrics to otel histogram payloads', function () {
    $transport = new CaptureOtelTransport();
    $exporter = new OtelExporter(transport: $transport);

    $exporter->export([Histogram::create('inference.client.token.usage.total', 42)]);

    expect($transport->payloads['metrics']['resourceMetrics'][0]['scopeMetrics'][0]['metrics'][0])
        ->toHaveKey('histogram');
});

it('maps gauge metrics to otel gauge payloads', function () {
    $transport = new CaptureOtelTransport();
    $exporter = new OtelExporter(transport: $transport);

    $exporter->export([Gauge::create('agent.active_executions', 3)]);

    expect($transport->payloads['metrics']['resourceMetrics'][0]['scopeMetrics'][0]['metrics'][0])
        ->toHaveKey('gauge');
});

it('maps timer metrics to otel histogram payloads', function () {
    $transport = new CaptureOtelTransport();
    $exporter = new OtelExporter(transport: $transport);

    $exporter->export([Timer::create('llm.inference.duration_ms', 250.5)]);

    expect($transport->payloads['metrics']['resourceMetrics'][0]['scopeMetrics'][0]['metrics'][0])
        ->toHaveKey('histogram');
});

it('adds logfire-specific attributes at the adapter boundary', function () {
    $exporter = new LogfireExporter(transport: new CaptureOtelTransport());
    $reference = SpanReference::fromContext(TraceContext::fresh());
    $observation = Observation::span($reference, 'structured_output.execute', AttributeBag::empty());

    $exporter->exportObservation($observation);

    $attributes = $exporter->tracesPayload()['resourceSpans'][0]['scopeSpans'][0]['spans'][0]['attributes'];
    $keys = array_map(fn(array $attribute): string => $attribute['key'], $attributes);

    expect($keys)->toContain('logfire.msg', 'logfire.msg_template', 'logfire.level_num', 'logfire.span_type');
});

it('fails fast when logfire exporter is created without config or transport', function () {
    expect(fn() => new LogfireExporter())
        ->toThrow(InvalidArgumentException::class, 'LogfireExporter requires either LogfireConfig or transport.');
});

it('maps observations to langfuse otel payloads with semantic attributes', function () {
    $transport = new CaptureLangfuseTransport();
    $exporter = new LangfuseExporter(transport: $transport);
    $reference = SpanReference::fromContext(TraceContext::fresh());

    $exporter->exportObservation(Observation::span($reference, 'agent.execute', AttributeBag::empty()));
    $exporter->flush();

    expect($transport->payloads)->toHaveKey('traces');
    expect($transport->payloads['traces']['resourceSpans'][0]['scopeSpans'][0]['spans'][0]['name'])
        ->toBe('agent.execute');
});

it('fans out one canonical observation to multiple adapters', function () {
    $otel = new OtelExporter();
    $logfire = new LogfireExporter(transport: new CaptureOtelTransport());
    $langfuse = new LangfuseExporter();
    $composite = new CompositeTelemetryExporter([$otel, $logfire, $langfuse]);
    $reference = SpanReference::fromContext(TraceContext::fresh());
    $observation = Observation::span($reference, 'agent.execute', AttributeBag::empty());

    $composite->exportObservation($observation);

    expect($otel->observations())->toHaveCount(1);
    expect($logfire->observations())->toHaveCount(1);
    expect($langfuse->observations())->toHaveCount(1);
});
