<?php declare(strict_types=1);

use Cognesy\Telemetry\Domain\Trace\TraceContext;
use Cognesy\Telemetry\Infrastructure\Continuation\TelemetryContinuationSerializer;
use Cognesy\Telemetry\Domain\Continuation\TelemetryContinuation;

it('creates a fresh trace context with valid ids', function () {
    $context = TraceContext::fresh();

    expect($context->traceId()->value())->toMatch('/^[0-9a-f]{32}$/');
    expect($context->spanId()->value())->toMatch('/^[0-9a-f]{16}$/');
    expect($context->traceparent())->toStartWith('00-');
});

it('serializes and deserializes telemetry continuation', function () {
    $serializer = new TelemetryContinuationSerializer();
    $continuation = new TelemetryContinuation(
        context: TraceContext::fresh(),
        correlation: ['session_id' => 'abc', 'execution_id' => 'def'],
    );

    $restored = $serializer->decode($serializer->encode($continuation));

    expect($restored->context()->traceparent())->toBe($continuation->context()->traceparent());
    expect($restored->correlation())->toBe($continuation->correlation());
});
