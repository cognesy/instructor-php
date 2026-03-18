<?php declare(strict_types=1);

use Cognesy\Metrics\Data\Histogram;
use Cognesy\Metrics\Data\Tags;
use Cognesy\Telemetry\Adapters\Langfuse\LangfusePayloadMapper;
use Cognesy\Telemetry\Domain\Observation\Observation;
use Cognesy\Telemetry\Domain\Observation\ObservationStatus;
use Cognesy\Telemetry\Domain\Trace\SpanReference;
use Cognesy\Telemetry\Domain\Trace\TraceContext;
use Cognesy\Telemetry\Domain\Value\AttributeBag;

it('projects correlated token metrics into langfuse usage details', function () {
    $mapper = new LangfusePayloadMapper();
    $reference = SpanReference::fromContext(TraceContext::fresh());
    $observation = Observation::span(
        $reference,
        'llm.inference',
        AttributeBag::empty()->with('inference.execution.id', 'exec-1'),
    );

    $payload = $mapper->tracesPayload(
        [$observation],
        [
            Histogram::create('inference.client.token.usage.input', 11, ['inference.execution.id' => 'exec-1']),
            Histogram::create('inference.client.token.usage.output', 7, ['inference.execution.id' => 'exec-1']),
            Histogram::create('inference.client.token.usage.total', 18, ['inference.execution.id' => 'exec-1', 'inference.usage.final' => true]),
        ],
    );

    $attributes = payloadAttributes($payload, 0);

    expect($attributes['langfuse.observation.usage_details'] ?? null)->toBe('{"input":11,"output":7,"total":18}');
});

it('orders nested observations topologically before exporting to langfuse', function () {
    $mapper = new LangfusePayloadMapper();
    $root = SpanReference::fromContext(TraceContext::fresh());
    $child = SpanReference::childOf($root);
    $grandchild = SpanReference::childOf($child);
    $grandchildEvent = SpanReference::childOf($grandchild);

    $payload = $mapper->tracesPayload([
        Observation::log($grandchildEvent, 'grandchild.event', AttributeBag::empty(), new DateTimeImmutable('2026-03-18T00:00:03Z')),
        Observation::span($grandchild, 'grandchild', AttributeBag::empty(), new DateTimeImmutable('2026-03-18T00:00:02Z')),
        Observation::span($root, 'root', AttributeBag::empty(), new DateTimeImmutable('2026-03-18T00:00:04Z')),
        Observation::span($child, 'child', AttributeBag::empty(), new DateTimeImmutable('2026-03-18T00:00:01Z')),
    ]);

    $names = array_column($payload['resourceSpans'][0]['scopeSpans'][0]['spans'], 'name');

    expect($names)->toBe(['root', 'child', 'grandchild', 'grandchild.event']);
});

it('marks error observations with langfuse error level metadata', function () {
    $mapper = new LangfusePayloadMapper();
    $reference = SpanReference::fromContext(TraceContext::fresh());
    $payload = $mapper->tracesPayload([
        Observation::log(
            span: $reference,
            name: 'agent.failure',
            attributes: AttributeBag::empty()->with('error.message', 'boom'),
            status: ObservationStatus::Error,
        ),
    ]);

    $attributes = payloadAttributes($payload, 0);

    expect($attributes['langfuse.observation.level'] ?? null)->toBe('ERROR');
});

/**
 * @return array<string, mixed>
 */
function payloadAttributes(array $payload, int $index): array
{
    $attributes = $payload['resourceSpans'][0]['scopeSpans'][0]['spans'][$index]['attributes'] ?? [];

    return array_reduce($attributes, function (array $carry, array $attribute): array {
        $value = $attribute['value'] ?? [];
        $carry[$attribute['key']] = $value['stringValue']
            ?? $value['intValue']
            ?? $value['boolValue']
            ?? $value['doubleValue']
            ?? $value['arrayValue']
            ?? null;

        return $carry;
    }, []);
}
