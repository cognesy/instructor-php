<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Adapters\Langfuse;

use Cognesy\Metrics\Data\Metric;
use Cognesy\Telemetry\Adapters\OTel\OtelPayloadMapper;
use Cognesy\Telemetry\Domain\Observation\Observation;
use Cognesy\Telemetry\Domain\Observation\ObservationKind;
use Cognesy\Telemetry\Domain\Observation\ObservationStatus;
use Cognesy\Telemetry\Domain\Value\AttributeBag;
use Cognesy\Utils\Json\Json;

final readonly class LangfusePayloadMapper
{
    private OtelPayloadMapper $otel;

    public function __construct(
        string $serviceName = 'instructor-php',
    ) {
        $this->otel = new OtelPayloadMapper($serviceName);
    }

    /**
     * @param list<Observation> $observations
     * @param list<Metric> $metrics
     */
    public function tracesPayload(array $observations, array $metrics = []): array
    {
        $decorated = array_map(
            fn(Observation $observation): Observation => $this->decorateObservation(
                $observation,
                $this->metricAttributes($observation, $metrics),
            ),
            $observations,
        );

        return $this->otel->tracesPayload($this->topologicallySort($decorated));
    }

    /** @param array<string, scalar|array<array-key, scalar>|null> $metricAttributes */
    private function decorateObservation(Observation $observation, array $metricAttributes = []): Observation
    {
        $attributes = [...$observation->attributes()->toArray(), ...$metricAttributes];
        $statusMessage = $attributes['error.message'] ?? null;
        $langfuseAttributes = array_filter([
            'langfuse.internal.as_root' => $observation->spanReference()->parentSpanId() === null ? true : null,
            'langfuse.trace.name' => $observation->spanReference()->parentSpanId() === null ? $observation->name() : null,
            'session.id' => $attributes['telemetry.session_id'] ?? null,
            'user.id' => $attributes['telemetry.user_id'] ?? null,
            'langfuse.trace.tags' => $attributes['telemetry.tags'] ?? null,
            'langfuse.trace.input' => $observation->spanReference()->parentSpanId() === null ? ($attributes['telemetry.io.input'] ?? null) : null,
            'langfuse.trace.output' => $observation->spanReference()->parentSpanId() === null ? ($attributes['telemetry.io.output'] ?? null) : null,
            'langfuse.trace.metadata' => $this->traceMetadata($attributes),
            'langfuse.observation.type' => $this->observationType($observation),
            'langfuse.observation.input' => $attributes['telemetry.io.input'] ?? null,
            'langfuse.observation.output' => $attributes['telemetry.io.output'] ?? null,
            'langfuse.observation.status_message' => is_string($statusMessage) ? $statusMessage : null,
            'langfuse.observation.level' => $observation->status() === ObservationStatus::Error ? 'ERROR' : null,
            'langfuse.observation.model.name' => $attributes['inference.response.model'] ?? null,
            'langfuse.observation.usage_details' => $this->usageDetails($attributes),
            'langfuse.observation.metadata' => $this->observationMetadata($attributes),
        ], static fn(mixed $value): bool => $value !== null && $value !== []);

        $decorated = AttributeBag::fromArray($attributes)->merge(AttributeBag::fromArray($langfuseAttributes));

        return match ($observation->kind()) {
            ObservationKind::Span => Observation::span(
                span: $observation->spanReference(),
                name: $observation->name(),
                attributes: $decorated,
                startedAt: $observation->startedAt(),
                endedAt: $observation->endedAt(),
                status: $observation->status(),
            ),
            ObservationKind::Log => Observation::log(
                span: $observation->spanReference(),
                name: $observation->name(),
                attributes: $decorated,
                at: $observation->startedAt(),
                status: $observation->status(),
            ),
        };
    }

    private function observationType(Observation $observation): string
    {
        $attributes = $observation->attributes()->toArray();
        $operationType = is_string($attributes['telemetry.operation.type'] ?? null)
            ? $attributes['telemetry.operation.type']
            : '';

        return match (true) {
            $observation->kind() === ObservationKind::Log => 'event',
            str_starts_with($operationType, 'llm.inference.attempt') => 'generation',
            str_starts_with($operationType, 'agent.execution'),
            str_starts_with($operationType, 'agent_ctrl.execution') => 'agent',
            str_contains($operationType, 'tool') => 'tool',
            default => 'span',
        };
    }

    /** @param array<string, scalar|array<array-key, scalar>|null> $attributes */
    private function usageDetails(array $attributes): ?string
    {
        $usage = array_filter([
            'input' => $this->intValue($attributes['inference.tokens.input'] ?? null),
            'output' => $this->intValue($attributes['inference.tokens.output'] ?? null),
            'total' => $this->intValue($attributes['inference.tokens.total'] ?? null),
        ], static fn(mixed $value): bool => $value !== null);

        return match ($usage) {
            [] => null,
            default => Json::encode($usage),
        };
    }

    /** @param array<string, scalar|array<array-key, scalar>|null> $attributes */
    private function intValue(mixed $value): ?int
    {
        return match (true) {
            is_int($value) => $value,
            is_numeric($value) => (int) $value,
            default => null,
        };
    }

    /**
     * @param list<Metric> $metrics
     * @return array<string, int|float>
     */
    private function metricAttributes(Observation $observation, array $metrics): array
    {
        if ($metrics === [] || $observation->kind() !== ObservationKind::Span) {
            return [];
        }

        $attributes = [];
        foreach ($this->selectMetrics($observation, $metrics) as $metric) {
            $attributes = [...$attributes, ...$this->attributesForMetric($metric)];
        }

        return $attributes;
    }

    /**
     * @param list<Metric> $metrics
     * @return list<Metric>
     */
    private function selectMetrics(Observation $observation, array $metrics): array
    {
        $selected = [];

        foreach ($metrics as $metric) {
            if (!$this->matchesMetric($observation, $metric)) {
                continue;
            }

            $name = $metric->name();
            $selected[$name] = match (true) {
                !isset($selected[$name]) => $metric,
                $this->shouldReplaceMetric($metric, $selected[$name]) => $metric,
                default => $selected[$name],
            };
        }

        return array_values($selected);
    }

    private function matchesMetric(Observation $observation, Metric $metric): bool
    {
        $observationAttributes = $observation->attributes()->toArray();
        $operationId = $observationAttributes['telemetry.operation.id'] ?? null;
        $executionId = $observationAttributes['inference.execution.id'] ?? null;
        $metricTarget = $metric->tags()->get('telemetry.parent_operation_id')
            ?? $metric->tags()->get('telemetry.root_operation_id')
            ?? $metric->tags()->get('inference.execution.id');

        return match (true) {
            is_string($operationId) && $metricTarget === $operationId => true,
            is_string($executionId) && $metricTarget === $executionId => true,
            default => false,
        };
    }

    private function shouldReplaceMetric(Metric $candidate, Metric $current): bool
    {
        $candidateFinal = $this->isFinalMetric($candidate);
        $currentFinal = $this->isFinalMetric($current);

        return match (true) {
            $candidateFinal !== $currentFinal => $candidateFinal,
            default => $candidate->timestamp() >= $current->timestamp(),
        };
    }

    private function isFinalMetric(Metric $metric): bool
    {
        return $metric->tags()->get('inference.usage.final') === true;
    }

    /** @return array<string, int|float> */
    private function attributesForMetric(Metric $metric): array
    {
        $value = $this->intValue($metric->value());
        if ($value === null) {
            return [];
        }

        return match ($metric->name()) {
            'inference.client.token.usage.input' => ['inference.tokens.input' => $value],
            'inference.client.token.usage.output' => ['inference.tokens.output' => $value],
            'inference.client.token.usage.total' => ['inference.tokens.total' => $value],
            default => [],
        };
    }

    /** @param array<string, scalar|array<array-key, scalar>|null> $attributes */
    private function traceMetadata(array $attributes): string
    {
        return Json::encode([
            'root_operation_id' => $attributes['telemetry.root_operation_id'] ?? null,
            'tags' => $attributes['telemetry.tags'] ?? [],
        ]);
    }

    /** @param array<string, scalar|array<array-key, scalar>|null> $attributes */
    private function observationMetadata(array $attributes): string
    {
        return Json::encode($attributes);
    }

    /** @param list<Observation> $observations @return list<Observation> */
    private function topologicallySort(array $observations): array
    {
        if ($observations === []) {
            return [];
        }

        $lookup = $this->lookup($observations);
        $children = $this->children($observations, $lookup);
        $ordered = [];
        foreach ($children['__root__'] ?? [] as $spanId) {
            $ordered = [...$ordered, ...$this->branch($spanId, $lookup, $children)];
        }

        return $ordered;
    }

    /** @param list<Observation> $observations @return array<string, Observation> */
    private function lookup(array $observations): array
    {
        $lookup = [];
        foreach ($observations as $observation) {
            $lookup[$observation->spanReference()->spanId()->value()] = $observation;
        }

        return $lookup;
    }

    /**
     * @param list<Observation> $observations
     * @param array<string, Observation> $lookup
     * @return array<string, list<string>>
     */
    private function children(array $observations, array $lookup): array
    {
        $grouped = ['__root__' => []];
        foreach ($observations as $position => $observation) {
            $grouped[$this->parentKey($observation, $lookup)][] = [
                'id' => $observation->spanReference()->spanId()->value(),
                'position' => $position,
                'observation' => $observation,
            ];
        }

        foreach ($grouped as $parentKey => $items) {
            usort($items, fn(array $left, array $right): int => $this->compareSiblings($left, $right));
            $grouped[$parentKey] = array_map(fn(array $item): string => $item['id'], $items);
        }

        return $grouped;
    }

    /** @param array<string, Observation> $lookup */
    private function parentKey(Observation $observation, array $lookup): string
    {
        $parentSpanId = $observation->spanReference()->parentSpanId()?->value();

        return match (true) {
            $parentSpanId === null => '__root__',
            isset($lookup[$parentSpanId]) => $parentSpanId,
            default => '__root__',
        };
    }

    /**
     * @param array{id: string, position: int, observation: Observation} $left
     * @param array{id: string, position: int, observation: Observation} $right
     */
    private function compareSiblings(array $left, array $right): int
    {
        return match (true) {
            $left['observation']->kind() !== $right['observation']->kind() => $left['observation']->kind() === ObservationKind::Span ? -1 : 1,
            $left['observation']->startedAt() != $right['observation']->startedAt() => $left['observation']->startedAt() <=> $right['observation']->startedAt(),
            default => $left['position'] <=> $right['position'],
        };
    }

    /**
     * @param array<string, Observation> $lookup
     * @param array<string, list<string>> $children
     * @return list<Observation>
     */
    private function branch(string $spanId, array $lookup, array $children): array
    {
        $ordered = [$lookup[$spanId]];
        foreach ($children[$spanId] ?? [] as $childSpanId) {
            $ordered = [...$ordered, ...$this->branch($childSpanId, $lookup, $children)];
        }

        return $ordered;
    }
}
