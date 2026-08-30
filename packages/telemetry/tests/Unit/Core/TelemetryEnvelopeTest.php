<?php declare(strict_types=1);

use Cognesy\Events\Event;
use Cognesy\Telemetry\Application\Projector\Support\EventData;
use Cognesy\Telemetry\Domain\Envelope\CaptureMode;
use Cognesy\Telemetry\Domain\Envelope\CapturePolicy;
use Cognesy\Telemetry\Domain\Envelope\OperationCorrelation;
use Cognesy\Telemetry\Domain\Envelope\OperationDescriptor;
use Cognesy\Telemetry\Domain\Envelope\OperationIO;
use Cognesy\Telemetry\Domain\Envelope\OperationKind;
use Cognesy\Telemetry\Domain\Envelope\TelemetryEnvelope;
use Cognesy\Telemetry\Domain\Trace\TraceContext;

it('round-trips telemetry envelope arrays through typed value objects', function () {
    $trace = TraceContext::fresh();
    $envelope = (new TelemetryEnvelope(
        operation: new OperationDescriptor(
            id: 'agent-step-1',
            type: 'agent.step',
            name: 'agent.step',
            kind: OperationKind::Span,
        ),
        correlation: OperationCorrelation::child(
            rootOperationId: 'agent-exec-1',
            parentOperationId: 'agent-exec-1',
            sessionId: 'session-1',
            requestId: 'request-1',
        ),
    ))
        ->withTrace($trace)
        ->withCapture(new CapturePolicy(CaptureMode::Summary, CaptureMode::Full, CaptureMode::Summary))
        ->withIO(new OperationIO(input: ['prompt' => 'hello'], output: ['text' => 'done']))
        ->withTags(['agent', 'step'])
        ->withMetadata(['driver' => 'codex']);

    $serialized = $envelope->toArray();
    $rehydrated = TelemetryEnvelope::fromArray($serialized);

    expect($rehydrated->operation()->id())->toBe('agent-step-1');
    expect($rehydrated->correlation()->rootOperationId())->toBe('agent-exec-1');
    expect($rehydrated->capture()?->output())->toBe(CaptureMode::Full);
    expect($rehydrated->io()?->output())->toBe(['text' => 'done']);
    expect($rehydrated->tags())->toBe(['agent', 'step']);
    expect($rehydrated->metadata())->toBe(['driver' => 'codex']);
    expect($rehydrated->trace()?->traceparent())->toBe($trace->traceparent());
});

it('extracts telemetry envelope from event payload data', function () {
    $envelope = new TelemetryEnvelope(
        operation: new OperationDescriptor(
            id: 'structured-output-1',
            type: 'structured_output.execution',
            name: 'structured_output.execute',
            kind: OperationKind::RootSpan,
        ),
        correlation: OperationCorrelation::root('structured-output-1', requestId: 'request-42'),
    );

    $event = new Event([
        'executionId' => 'structured-output-1',
        TelemetryEnvelope::KEY => $envelope->toArray(),
    ]);

    $resolved = EventData::telemetry(EventData::of($event));

    expect($resolved)->toBeInstanceOf(TelemetryEnvelope::class);
    expect($resolved?->operation()->name())->toBe('structured_output.execute');
    expect($resolved?->correlation()->requestId())->toBe('request-42');
});

it('round-trips trace context arrays explicitly', function () {
    $context = TraceContext::fresh();

    expect(TraceContext::fromArray($context->toArray())->traceparent())
        ->toBe($context->traceparent());
});
