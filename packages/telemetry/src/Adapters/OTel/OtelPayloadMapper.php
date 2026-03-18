<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Adapters\OTel;

use Cognesy\Metrics\Data\Metric;
use Cognesy\Telemetry\Domain\Observation\Observation;
use Cognesy\Telemetry\Domain\Observation\ObservationStatus;
use Cognesy\Utils\Json\Json;

final readonly class OtelPayloadMapper
{
    public function __construct(
        private string $serviceName = 'instructor-php',
    ) {}

    /** @param list<Observation> $observations */
    public function tracesPayload(array $observations): array
    {
        return [
            'resourceSpans' => [[
                'resource' => [
                    'attributes' => $this->attributes(['service.name' => $this->serviceName]),
                ],
                'scopeSpans' => [[
                    'scope' => ['name' => 'cognesy.telemetry'],
                    'spans' => array_map($this->spanPayload(...), $observations),
                ]],
            ]],
        ];
    }

    /** @param iterable<Metric> $metrics */
    public function metricsPayload(iterable $metrics): array
    {
        $list = is_array($metrics) ? $metrics : iterator_to_array($metrics);

        return [
            'resourceMetrics' => [[
                'resource' => [
                    'attributes' => $this->attributes(['service.name' => $this->serviceName]),
                ],
                'scopeMetrics' => [[
                    'scope' => ['name' => 'cognesy.telemetry'],
                    'metrics' => array_map($this->metricPayload(...), $list),
                ]],
            ]],
        ];
    }

    /** @return array<string, mixed> */
    public function spanPayload(Observation $observation): array
    {
        $status = match ($observation->status()) {
            ObservationStatus::Ok => ['code' => 1],
            ObservationStatus::Error => ['code' => 2],
        };

        return array_filter([
            'traceId' => $observation->spanReference()->traceId()->value(),
            'spanId' => $observation->spanReference()->spanId()->value(),
            'parentSpanId' => $observation->spanReference()->parentSpanId()?->value(),
            'name' => $observation->name(),
            'startTimeUnixNano' => $this->unixNanos($observation->startedAt()),
            'endTimeUnixNano' => $this->unixNanos($observation->endedAt()),
            'attributes' => $this->attributes($observation->attributes()->toArray()),
            'status' => $status,
        ], static fn(mixed $value): bool => $value !== null);
    }

    /** @return array<string, mixed> */
    public function metricPayload(Metric $metric): array
    {
        $timeNano = $this->unixNanos($metric->timestamp());
        $basePoint = array_filter([
            'attributes' => $this->attributes($metric->tags()->toArray()),
            'startTimeUnixNano' => $timeNano,
            'timeUnixNano' => $timeNano,
        ], static fn(mixed $value): bool => $value !== null);

        $scalarPoint = [...$basePoint, ...$this->numberValue($metric->value())];

        $histogramPoint = [
            ...$basePoint,
            'count' => '1',
            'sum' => (float) $metric->value(),
            'explicitBounds' => [],
            'bucketCounts' => ['1'],
        ];

        return match ($metric->type()) {
            'counter' => [
                'name' => $metric->name(),
                'sum' => [
                    'dataPoints' => [$scalarPoint],
                    'isMonotonic' => true,
                    'aggregationTemporality' => 2,
                ],
            ],
            'gauge' => [
                'name' => $metric->name(),
                'gauge' => [
                    'dataPoints' => [$scalarPoint],
                ],
            ],
            'histogram', 'timer' => [
                'name' => $metric->name(),
                'histogram' => [
                    'dataPoints' => [$histogramPoint],
                    'aggregationTemporality' => 2,
                ],
            ],
            default => [
                'name' => $metric->name(),
                'gauge' => [
                    'dataPoints' => [$scalarPoint],
                ],
            ],
        };
    }

    /** @param array<string, scalar|array<array-key, scalar>|null> $values */
    private function attributes(array $values): array
    {
        return array_map(
            fn(string $key, mixed $value): array => [
                'key' => $key,
                'value' => $this->attributeValue($value),
            ],
            array_keys($values),
            $values,
        );
    }

    /** @return array<string, mixed> */
    private function attributeValue(mixed $value): array
    {
        return match (true) {
            is_bool($value) => ['boolValue' => $value],
            is_int($value) => ['intValue' => (string) $value],
            is_float($value) => ['doubleValue' => $value],
            is_array($value) && !array_is_list($value) => ['stringValue' => Json::encode($value)],
            is_array($value) => [
                'arrayValue' => [
                    'values' => array_map(fn(mixed $item): array => $this->attributeValue($item), $value),
                ],
            ],
            $value === null => ['stringValue' => ''],
            default => ['stringValue' => (string) $value],
        };
    }

    /** @return array<string, float|string> */
    private function numberValue(float|int $value): array
    {
        return match (true) {
            is_int($value) => ['asInt' => (string) $value],
            default => ['asDouble' => $value],
        };
    }

    private function unixNanos(\DateTimeImmutable $at): string
    {
        $seconds = (int) $at->format('U');
        $micros = (int) $at->format('u');

        return (string) (($seconds * 1_000_000_000) + ($micros * 1_000));
    }
}
