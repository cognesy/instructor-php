---
title: 'Streaming metrics (Polyglot)'
docname: 'metrics_streaming'
---

## Overview

Collect simple streaming metrics from Polyglot inference events: time to first chunk,
stream duration, chunk count (streamed deltas), and average output tokens per second.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Metrics\Collectors\MetricsCollector;
use Cognesy\Metrics\Data\Metric;
use Cognesy\Metrics\Exporters\CallbackExporter;
use Cognesy\Metrics\Metrics;
use Cognesy\Polyglot\Inference\Events\InferenceCompleted;
use Cognesy\Polyglot\Inference\Events\PartialInferenceResponseCreated;
use Cognesy\Polyglot\Inference\Events\StreamFirstChunkReceived;
use Cognesy\Polyglot\Inference\Inference;

final class StreamMetricsCollector extends MetricsCollector
{
    private int $chunkCount = 0;

    protected function listeners(): array {
        return [
            StreamFirstChunkReceived::class => 'onFirstChunk',
            PartialInferenceResponseCreated::class => 'onChunk',
            InferenceCompleted::class => 'onCompleted',
        ];
    }

    public function onFirstChunk(StreamFirstChunkReceived $event): void {
        $this->timer('llm.stream.ttfc_ms', $event->timeToFirstChunkMs, [
            'model' => $this->modelTag($event->model),
        ]);
    }

    public function onChunk(PartialInferenceResponseCreated $event): void {
        $this->chunkCount += 1;
    }

    public function onCompleted(InferenceCompleted $event): void {
        $durationSeconds = max(0.001, $event->durationMs / 1000);
        $outputTokens = $event->usage->output();
        $tokensPerSecond = $outputTokens / $durationSeconds;

        $this->timer('llm.stream.duration_ms', $event->durationMs);
        $this->gauge('llm.stream.chunk_count', (float) $this->chunkCount);
        $this->gauge('llm.stream.output_tokens', (float) $outputTokens);
        $this->gauge('llm.stream.output_tokens_per_second', $tokensPerSecond);

        $this->chunkCount = 0;
    }

    private function modelTag(?string $model): string {
        if ($model !== null && $model !== '') {
            return $model;
        }
        return 'default';
    }
}

$events = new EventDispatcher();
$metrics = new Metrics($events);
$metrics->collect(new StreamMetricsCollector());

$metrics->exportTo(new CallbackExporter(function (iterable $metrics): void {
    $aggregates = aggregateMetrics($metrics);
    foreach ($aggregates as $aggregate) {
        $tagsOutput = formatTags($aggregate['tags']);
        $value = aggregatedValue($aggregate);
        printf("[%s] %s%s = %.2f\n", $aggregate['type'], $aggregate['name'], $tagsOutput, $value);
    }
}));

$prompt = 'In one sentence, explain why streaming responses help UX.';
$stream = (new Inference($events))
    ->withMessages($prompt)
    ->withOptions(['max_tokens' => 64])
    ->withStreaming()
    ->stream()
    ->responses();

echo "USER: {$prompt}\n";
echo "ASSISTANT: ";
foreach ($stream as $partial) {
    echo $partial->contentDelta;
}
echo "\n\n";

$metrics->export();

function formatTags(array $tags): string {
    if ($tags === []) {
        return '';
    }

    $keys = array_keys($tags);
    $values = array_values($tags);
    $tagList = array_map(
        static fn (string $key, mixed $value): string => "{$key}=\"{$value}\"",
        $keys,
        $values,
    );

    return ' {' . implode(', ', $tagList) . '}';
}

/**
 * @param iterable<Metric> $metrics
 * @return array<int, array{type: string, name: string, tags: array, count: int, sum: float, last: float}>
 */
function aggregateMetrics(iterable $metrics): array {
    $aggregates = [];
    foreach ($metrics as $metric) {
        $key = $metric->type() . '|' . $metric->name() . '|' . $metric->tags()->toKey();
        if (!array_key_exists($key, $aggregates)) {
            $aggregates[$key] = [
                'type' => $metric->type(),
                'name' => $metric->name(),
                'tags' => $metric->tags()->toArray(),
                'count' => 0,
                'sum' => 0.0,
                'last' => 0.0,
            ];
        }

        $aggregates[$key]['count'] += 1;
        $aggregates[$key]['sum'] += $metric->value();
        $aggregates[$key]['last'] = $metric->value();
    }

    return array_values($aggregates);
}

/**
 * @param array{type: string, count: int, sum: float, last: float} $aggregate
 */
function aggregatedValue(array $aggregate): float {
    return match ($aggregate['type']) {
        'counter' => $aggregate['sum'],
        'timer', 'histogram' => $aggregate['sum'] / max(1, $aggregate['count']),
        default => $aggregate['last'],
    };
}
?>
```
