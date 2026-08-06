<?php declare(strict_types=1);

use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Agents\Events\AgentExecutionCompleted;
use Cognesy\Agents\Events\AgentExecutionFailed;
use Cognesy\Agents\Events\AgentExecutionStarted;
use Cognesy\Agents\Events\AgentStepCompleted;
use Cognesy\Agents\Events\AgentStepStarted;
use Cognesy\Agents\Events\SubagentCompleted;
use Cognesy\Agents\Events\SubagentSpawning;
use Cognesy\Agents\Events\TokenUsageReported;
use Cognesy\Agents\Events\ToolCallBlocked;
use Cognesy\Agents\Events\ToolCallCompleted;
use Cognesy\Agents\Events\ToolCallStarted;
use Cognesy\Agents\Telemetry\AgentsTelemetryProjector;
use Cognesy\Metrics\Contracts\CanExportMetrics;
use Cognesy\Metrics\Data\Metric;
use Cognesy\Polyglot\Embeddings\Events\EmbeddingsFailed;
use Cognesy\Polyglot\Embeddings\Events\EmbeddingsRequested;
use Cognesy\Polyglot\Embeddings\Events\EmbeddingsResponseReceived;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptFailed;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptStarted;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptSucceeded;
use Cognesy\Polyglot\Inference\Events\InferenceCompleted;
use Cognesy\Polyglot\Inference\Events\InferenceFailed;
use Cognesy\Polyglot\Inference\Events\InferenceStarted;
use Cognesy\Polyglot\Inference\Events\InferenceUsageReported;
use Cognesy\Polyglot\Telemetry\PolyglotTelemetryProjector;
use Cognesy\Telemetry\Application\Registry\TraceRegistry;
use Cognesy\Telemetry\Application\Telemetry;
use Cognesy\Telemetry\Domain\Contract\CanExportObservations;
use Cognesy\Telemetry\Domain\Metric\MetricNames;
use Cognesy\Telemetry\Domain\Observation\Observation;

/**
 * Captures every metric that reaches the exporter boundary (observations are accepted but
 * discarded — this file is only about the metric catalog). Metrics are buffered by Telemetry
 * and only handed to a CanExportMetrics exporter on flush(), so every test below must call
 * flush() before inspecting $exporter->metrics.
 */
final class RuntimeMetricCatalogExporter implements CanExportObservations, CanExportMetrics
{
    /** @var list<Metric> */
    public array $metrics = [];

    #[\Override]
    public function exportObservation(Observation $observation): void {
        // not under test here — RuntimeMetricCatalogTest pins metrics only.
    }

    /** @param iterable<Metric> $metrics */
    #[\Override]
    public function export(iterable $metrics): void {
        foreach ($metrics as $metric) {
            $this->metrics[] = $metric;
        }
    }
}

/** @return array{0: Telemetry, 1: RuntimeMetricCatalogExporter} */
function metricCatalogHub(): array {
    $exporter = new RuntimeMetricCatalogExporter();
    $telemetry = new Telemetry(new TraceRegistry(), $exporter);
    return [$telemetry, $exporter];
}

/** @return list<Metric> */
function metricsNamed(RuntimeMetricCatalogExporter $exporter, string $name): array {
    return array_values(array_filter(
        $exporter->metrics,
        fn(Metric $metric): bool => $metric->name() === $name,
    ));
}

/**
 * Drives a representative multi-event run through both projectors sharing one hub, the way
 * a real agent execution backed by an LLM call would. Used by the cross-cutting invariant
 * tests below, which must see a realistic mix of metric names rather than a hand-picked list.
 */
function realisticCatalogRun(): RuntimeMetricCatalogExporter {
    [$telemetry, $exporter] = metricCatalogHub();
    $polyglot = new PolyglotTelemetryProjector($telemetry);
    $agents = new AgentsTelemetryProjector($telemetry);

    // Inference: a successful call ...
    $polyglot->project(InferenceStarted::fromLifecycle('inf-1', 'req-1', false, 'gpt-x', 2));
    $polyglot->project(new InferenceAttemptStarted('inf-1', 'att-1', 1, 'gpt-x'));
    $polyglot->project(InferenceAttemptSucceeded::fromLifecycle(
        executionId: 'inf-1', attemptId: 'att-1', attemptNumber: 1, finishReason: 'stop',
        durationMs: 12.5, usage: new InferenceUsage(inputTokens: 10, outputTokens: 20),
    ));
    $polyglot->project(InferenceUsageReported::fromLifecycle(
        executionId: 'inf-1', model: 'gpt-x', isFinal: true,
        usage: new InferenceUsage(inputTokens: 10, outputTokens: 20),
    ));
    $polyglot->project(InferenceCompleted::fromLifecycle(
        executionId: 'inf-1', isSuccess: true, finishReason: 'stop', durationMs: 23.0,
        attemptCount: 1, usage: new InferenceUsage(inputTokens: 10, outputTokens: 20),
    ));

    // ... and a retried-then-failed call.
    $polyglot->project(InferenceStarted::fromLifecycle('inf-2', 'req-2', false, 'gpt-y', 1));
    $polyglot->project(new InferenceAttemptStarted('inf-2', 'att-2', 1, 'gpt-y'));
    $polyglot->project(InferenceAttemptFailed::fromLifecycle(
        executionId: 'inf-2', attemptId: 'att-2', attemptNumber: 1, errorMessage: 'boom',
        errorType: 'RateLimitError', httpStatusCode: 429, willRetry: true, durationMs: 5.0,
    ));
    $polyglot->project(new InferenceFailed([
        'executionId' => 'inf-2', 'exception' => 'boom', 'context' => 'ctx', 'statusCode' => 429,
    ]));

    // Embeddings: one success, one failure.
    $polyglot->project(new EmbeddingsRequested(['request' => ['model' => 'embed-1', 'inputs' => ['a', 'b']]]));
    $polyglot->project(new EmbeddingsResponseReceived([
        'executionId' => 'emb-1', 'model' => 'embed-1', 'vectorCount' => 2, 'dimensions' => 1536,
        'usage' => ['total' => 42],
    ]));
    $polyglot->project(new EmbeddingsFailed(['exception' => 'boom', 'statusCode' => 503]));

    // Agent execution: a step with a completed tool call, a blocked tool call and a subagent.
    $usage = new InferenceUsage(inputTokens: 10, outputTokens: 5);
    $agents->project(new AgentExecutionStarted('agent-1', 'exec-1', null, 2, 3));
    $agents->project(new AgentStepStarted('agent-1', 'exec-1', null, 1, 2, 3));
    $agents->project(new TokenUsageReported('agent-1', 'exec-1', null, 'llm_call', $usage));
    $agents->project(new ToolCallStarted('agent-1', 'exec-1', null, 1, 'search', ['q' => 'x'], new DateTimeImmutable(), 'call-1'));
    $agents->project(new ToolCallCompleted(
        'agent-1', 'exec-1', null, 1, 'search', true, null,
        new DateTimeImmutable('-1 second'), new DateTimeImmutable(), 'result', 'call-1',
    ));
    $agents->project(new ToolCallBlocked('agent-1', 'exec-1', null, 1, 'danger', [], 'blocked by policy', 'safety-hook'));
    $agents->project(new SubagentSpawning('agent-1', 'helper', 'do X', 1, 3, 'exec-1', 1, 'call-1'));
    $agents->project(new SubagentCompleted(
        'agent-1', 'sub-1', 'helper', ExecutionStatus::Completed, 2, $usage,
        new DateTimeImmutable('-2 seconds'), 'exec-1', 1, 'call-1',
    ));
    $agents->project(new AgentStepCompleted(
        agentId: 'agent-1', executionId: 'exec-1', parentAgentId: null, stepNumber: 1,
        hasToolCalls: true, errorCount: 0, errorMessages: '', usage: $usage,
        finishReason: InferenceFinishReason::ToolCalls, startedAt: new DateTimeImmutable('-1 second'), durationMs: 15.0,
    ));
    $agents->project(new AgentExecutionCompleted('agent-1', 'exec-1', null, ExecutionStatus::Completed, 1, $usage, null));

    // ... and a second, failed agent execution.
    $agents->project(new AgentExecutionStarted('agent-2', 'exec-2', null, 1, 1));
    $agents->project(new AgentExecutionFailed(
        'agent-2', 'exec-2', null, new RuntimeException('boom'), ExecutionStatus::Failed, 1, $usage, 'boom',
    ));

    $telemetry->flush();

    return $exporter;
}

// ==========================================================================
// Polyglot — PolyglotTelemetryProjector
// ==========================================================================

it('emits token usage histograms from InferenceUsageReported, tagged with execution id, model and finality', function () {
    [$telemetry, $exporter] = metricCatalogHub();
    $projector = new PolyglotTelemetryProjector($telemetry);

    $projector->project(InferenceUsageReported::fromLifecycle(
        executionId: 'inf-1', model: 'gpt-x', isFinal: true,
        usage: new InferenceUsage(inputTokens: 10, outputTokens: 20),
    ));
    $telemetry->flush();

    $expectedTags = [
        'inference.execution.id' => 'inf-1',
        'inference.response.model' => 'gpt-x',
        'inference.usage.final' => true,
    ];

    $input = metricsNamed($exporter, MetricNames::TOKEN_USAGE_INPUT);
    $output = metricsNamed($exporter, MetricNames::TOKEN_USAGE_OUTPUT);
    $total = metricsNamed($exporter, MetricNames::TOKEN_USAGE_TOTAL);

    expect($input)->toHaveCount(1)
        ->and($input[0]->type())->toBe('histogram')
        ->and($input[0]->value())->toBe(10.0)
        ->and($input[0]->tags()->toArray())->toBe($expectedTags);

    expect($output[0]->type())->toBe('histogram')
        ->and($output[0]->value())->toBe(20.0)
        ->and($output[0]->tags()->toArray())->toBe($expectedTags);

    expect($total[0]->type())->toBe('histogram')
        ->and($total[0]->value())->toBe(30.0)
        ->and($total[0]->tags()->toArray())->toBe($expectedTags);
});

it('skips token usage histograms entirely when InferenceUsageReported carries no token counts', function () {
    [$telemetry, $exporter] = metricCatalogHub();
    $projector = new PolyglotTelemetryProjector($telemetry);

    $projector->project(new InferenceUsageReported([
        'executionId' => 'inf-2',
        'model' => 'gpt-x',
        'isFinal' => true,
        // deliberately no inputTokens / outputTokens / totalTokens keys
    ]));
    $telemetry->flush();

    expect($exporter->metrics)->toBe([]);
});

it('emits operation count and duration metrics for InferenceCompleted, with no Timer when durationMs is null or negative', function () {
    [$telemetry, $exporter] = metricCatalogHub();
    $projector = new PolyglotTelemetryProjector($telemetry);

    $projector->project(InferenceCompleted::fromLifecycle(
        executionId: 'inf-1', isSuccess: true, finishReason: 'stop', durationMs: 23.0,
        attemptCount: 1, usage: new InferenceUsage(inputTokens: 10, outputTokens: 20),
    ));
    $telemetry->flush();

    $counters = metricsNamed($exporter, 'inference.client.operation.count');
    $timers = metricsNamed($exporter, 'inference.client.operation.duration');
    $expectedTags = ['inference.finish_reason' => 'stop', 'inference.outcome' => 'success'];

    expect($counters)->toHaveCount(1)
        ->and($counters[0]->type())->toBe('counter')
        ->and($counters[0]->value())->toBe(1.0)
        ->and($counters[0]->tags()->toArray())->toBe($expectedTags);

    expect($timers)->toHaveCount(1)
        ->and($timers[0]->type())->toBe('timer')
        ->and($timers[0]->value())->toBe(23.0)
        ->and($timers[0]->tags()->toArray())->toBe($expectedTags);

    [$telemetry2, $exporter2] = metricCatalogHub();
    $projector2 = new PolyglotTelemetryProjector($telemetry2);
    $projector2->project(new InferenceCompleted([
        'executionId' => 'inf-1', 'isSuccess' => true, 'finishReason' => 'stop', 'attemptCount' => 1,
        // no durationMs key
    ]));
    $telemetry2->flush();

    expect(metricsNamed($exporter2, 'inference.client.operation.duration'))->toBe([]);
    expect(metricsNamed($exporter2, 'inference.client.operation.count'))->toHaveCount(1);

    // A clock that went backwards must not take down the call it is only observing: Timer
    // throws on a negative duration, so the projector must skip it rather than emit it.
    [$telemetry3, $exporter3] = metricCatalogHub();
    $projector3 = new PolyglotTelemetryProjector($telemetry3);
    $projector3->project(InferenceCompleted::fromLifecycle(
        executionId: 'inf-1', isSuccess: true, finishReason: 'stop', durationMs: -1.0,
        attemptCount: 1, usage: new InferenceUsage(inputTokens: 10, outputTokens: 20),
    ));
    $telemetry3->flush();

    expect(metricsNamed($exporter3, 'inference.client.operation.duration'))->toBe([]);
    expect(metricsNamed($exporter3, 'inference.client.operation.count'))->toHaveCount(1);
});

it('emits an operation-count failure metric for InferenceFailed, tagged with outcome and http status', function () {
    [$telemetry, $exporter] = metricCatalogHub();
    $projector = new PolyglotTelemetryProjector($telemetry);

    $projector->project(new InferenceFailed([
        'executionId' => 'inf-3', 'exception' => 'boom', 'context' => 'ctx', 'statusCode' => 500,
    ]));
    $telemetry->flush();

    $counters = metricsNamed($exporter, 'inference.client.operation.count');

    expect($counters)->toHaveCount(1)
        ->and($counters[0]->tags()->toArray())->toBe([
            'http.response.status_code' => 500,
            'inference.outcome' => 'failure',
        ]);
    expect(metricsNamed($exporter, 'inference.client.operation.duration'))->toBe([]);
});

it('emits attempt count and duration metrics for InferenceAttemptSucceeded and InferenceAttemptFailed, with no Timer when durationMs is null or negative', function () {
    [$telemetry, $exporter] = metricCatalogHub();
    $projector = new PolyglotTelemetryProjector($telemetry);

    $projector->project(InferenceAttemptSucceeded::fromLifecycle(
        executionId: 'inf-1', attemptId: 'att-1', attemptNumber: 1, finishReason: 'stop',
        durationMs: 12.5, usage: new InferenceUsage(inputTokens: 10, outputTokens: 20),
    ));
    $telemetry->flush();

    $counters = metricsNamed($exporter, 'inference.client.attempt.count');
    $timers = metricsNamed($exporter, 'inference.client.attempt.duration');

    expect($counters[0]->type())->toBe('counter')
        ->and($counters[0]->tags()->toArray())->toBe([
            'inference.finish_reason' => 'stop',
            'inference.outcome' => 'success',
        ]);
    expect($timers[0]->type())->toBe('timer')
        ->and($timers[0]->value())->toBe(12.5);

    [$telemetry2, $exporter2] = metricCatalogHub();
    $projector2 = new PolyglotTelemetryProjector($telemetry2);
    $projector2->project(InferenceAttemptFailed::fromLifecycle(
        executionId: 'inf-2', attemptId: 'att-2', attemptNumber: 1, errorMessage: 'boom',
        errorType: 'RateLimitError', httpStatusCode: 429, willRetry: true, durationMs: 5.0,
    ));
    $telemetry2->flush();

    $failCounters = metricsNamed($exporter2, 'inference.client.attempt.count');
    $failTimers = metricsNamed($exporter2, 'inference.client.attempt.duration');

    expect($failCounters[0]->tags()->toArray())->toBe([
        'error.type' => 'RateLimitError',
        'http.response.status_code' => 429,
        'inference.retry' => true,
        'inference.outcome' => 'failure',
    ]);
    expect($failTimers[0]->value())->toBe(5.0);

    [$telemetry3, $exporter3] = metricCatalogHub();
    $projector3 = new PolyglotTelemetryProjector($telemetry3);
    $projector3->project(new InferenceAttemptFailed([
        'executionId' => 'inf-2', 'attemptId' => 'att-2', 'attemptNumber' => 1,
        'errorMessage' => 'boom', 'errorType' => 'RateLimitError', 'httpStatusCode' => 429, 'willRetry' => true,
        // no durationMs key
    ]));
    $telemetry3->flush();

    expect(metricsNamed($exporter3, 'inference.client.attempt.duration'))->toBe([]);
    expect(metricsNamed($exporter3, 'inference.client.attempt.count'))->toHaveCount(1);

    // A clock that went backwards must not take down the attempt it is only observing.
    [$telemetry4, $exporter4] = metricCatalogHub();
    $projector4 = new PolyglotTelemetryProjector($telemetry4);
    $projector4->project(InferenceAttemptSucceeded::fromLifecycle(
        executionId: 'inf-1', attemptId: 'att-1', attemptNumber: 1, finishReason: 'stop',
        durationMs: -12.5, usage: new InferenceUsage(inputTokens: 10, outputTokens: 20),
    ));
    $telemetry4->flush();

    expect(metricsNamed($exporter4, 'inference.client.attempt.duration'))->toBe([]);
    expect(metricsNamed($exporter4, 'inference.client.attempt.count'))->toHaveCount(1);
});

it('emits embeddings operation-count and token-usage metrics for EmbeddingsResponseReceived', function () {
    [$telemetry, $exporter] = metricCatalogHub();
    $projector = new PolyglotTelemetryProjector($telemetry);

    $projector->project(new EmbeddingsResponseReceived([
        'executionId' => 'emb-1', 'model' => 'embed-1', 'vectorCount' => 2, 'dimensions' => 1536,
        'usage' => ['total' => 42],
    ]));
    $telemetry->flush();

    $counters = metricsNamed($exporter, 'inference.embeddings.operation.count');
    expect($counters)->toHaveCount(1)
        ->and($counters[0]->type())->toBe('counter')
        ->and($counters[0]->tags()->toArray())->toBe([
            'inference.outcome' => 'success',
            'inference.response.model' => 'embed-1',
        ]);

    $total = metricsNamed($exporter, MetricNames::TOKEN_USAGE_TOTAL);
    expect($total)->toHaveCount(1)
        ->and($total[0]->type())->toBe('histogram')
        ->and($total[0]->value())->toBe(42.0)
        ->and($total[0]->tags()->toArray())->toBe([
            'inference.execution.id' => 'emb-1',
            'inference.response.model' => 'embed-1',
            'inference.vector_count' => 2,
            'inference.vector_dimensions' => 1536,
        ]);
});

it('emits an embeddings operation-count failure metric for EmbeddingsFailed', function () {
    [$telemetry, $exporter] = metricCatalogHub();
    $projector = new PolyglotTelemetryProjector($telemetry);

    $projector->project(new EmbeddingsFailed(['exception' => 'boom', 'statusCode' => 503]));
    $telemetry->flush();

    $counters = metricsNamed($exporter, 'inference.embeddings.operation.count');
    expect($counters)->toHaveCount(1)
        ->and($counters[0]->tags()->toArray())->toBe([
            'inference.outcome' => 'failure',
            'http.response.status_code' => 503,
        ]);
});

it('emits no Gauge metrics anywhere in the polyglot projector', function () {
    [$telemetry, $exporter] = metricCatalogHub();
    $projector = new PolyglotTelemetryProjector($telemetry);

    $projector->project(InferenceUsageReported::fromLifecycle('inf-1', 'gpt-x', true, new InferenceUsage(10, 20)));
    $projector->project(InferenceCompleted::fromLifecycle('inf-1', true, 'stop', 23.0, 1, new InferenceUsage(10, 20)));
    $projector->project(new InferenceFailed(['executionId' => 'inf-1', 'exception' => 'boom', 'statusCode' => 500]));
    $projector->project(InferenceAttemptSucceeded::fromLifecycle('inf-1', 'att-1', 1, 'stop', 12.5, new InferenceUsage(10, 20)));
    $projector->project(InferenceAttemptFailed::fromLifecycle('inf-1', 'att-1', 1, 'boom', 'RateLimitError', 429, true, 5.0));
    $projector->project(new EmbeddingsResponseReceived([
        'executionId' => 'emb-1', 'model' => 'embed-1', 'vectorCount' => 2, 'dimensions' => 1536, 'usage' => ['total' => 42],
    ]));
    $projector->project(new EmbeddingsFailed(['exception' => 'boom', 'statusCode' => 503]));
    $telemetry->flush();

    expect($exporter->metrics)->not->toBeEmpty();
    foreach ($exporter->metrics as $metric) {
        expect($metric->type())->not->toBe('gauge');
    }
});

// ==========================================================================
// Agents — AgentsTelemetryProjector
// ==========================================================================

it('emits a token usage histogram for TokenUsageReported carrying agent and execution identifiers', function () {
    [$telemetry, $exporter] = metricCatalogHub();
    $projector = new AgentsTelemetryProjector($telemetry);

    $projector->project(new TokenUsageReported('agent-1', 'exec-1', 'parent-1', 'llm_call', new InferenceUsage(10, 5)));
    $telemetry->flush();

    $total = metricsNamed($exporter, MetricNames::TOKEN_USAGE_TOTAL);
    expect($total)->toHaveCount(1)
        ->and($total[0]->type())->toBe('histogram')
        ->and($total[0]->value())->toBe(15.0)
        ->and($total[0]->tags()->toArray())->toBe([
            'agent.id' => 'agent-1',
            'inference.execution.id' => 'exec-1',
            'agent.operation' => 'llm_call',
            'agent.parent_id' => 'parent-1',
        ]);
});

it('emits a Gauge for agent.context.message_count on AgentStepStarted, distinguishing subagents', function () {
    [$telemetry, $exporter] = metricCatalogHub();
    $projector = new AgentsTelemetryProjector($telemetry);

    $projector->project(new AgentExecutionStarted('agent-1', 'exec-1', null, 4, 3));
    $projector->project(new AgentStepStarted('agent-1', 'exec-1', null, 1, 4, 3));
    $telemetry->flush();

    $gauges = metricsNamed($exporter, 'agent.context.message_count');
    expect($gauges)->toHaveCount(1)
        ->and($gauges[0]->type())->toBe('gauge')
        ->and($gauges[0]->value())->toBe(4.0)
        ->and($gauges[0]->tags()->toArray())->toBe(['agent.is_subagent' => false]);

    [$telemetry2, $exporter2] = metricCatalogHub();
    $projector2 = new AgentsTelemetryProjector($telemetry2);
    $projector2->project(new AgentExecutionStarted('agent-2', 'exec-2', 'parent-2', 4, 3));
    $projector2->project(new AgentStepStarted('agent-2', 'exec-2', 'parent-2', 1, 4, 3));
    $telemetry2->flush();

    expect(metricsNamed($exporter2, 'agent.context.message_count')[0]->tags()->toArray())
        ->toBe(['agent.is_subagent' => true]);

    // isSubagent() treats '' the same as null — an empty parentAgentId is not a subagent.
    [$telemetry3, $exporter3] = metricCatalogHub();
    $projector3 = new AgentsTelemetryProjector($telemetry3);
    $projector3->project(new AgentExecutionStarted('agent-3', 'exec-3', '', 4, 3));
    $projector3->project(new AgentStepStarted('agent-3', 'exec-3', '', 1, 4, 3));
    $telemetry3->flush();

    expect(metricsNamed($exporter3, 'agent.context.message_count')[0]->tags()->toArray())
        ->toBe(['agent.is_subagent' => false]);
});

it('emits step count and duration metrics for AgentStepCompleted, with no Timer when durationMs is negative', function () {
    [$telemetry, $exporter] = metricCatalogHub();
    $projector = new AgentsTelemetryProjector($telemetry);

    $projector->project(new AgentStepCompleted(
        agentId: 'agent-1', executionId: 'exec-1', parentAgentId: null, stepNumber: 1,
        hasToolCalls: true, errorCount: 0, errorMessages: '', usage: new InferenceUsage(10, 5),
        finishReason: InferenceFinishReason::ToolCalls, startedAt: new DateTimeImmutable('-1 second'), durationMs: 15.0,
    ));
    $telemetry->flush();

    $counters = metricsNamed($exporter, 'agent.step.count');
    $timers = metricsNamed($exporter, 'agent.step.duration');
    $expectedTags = [
        'agent.has_tool_calls' => true,
        'inference.finish_reason' => 'tool_calls',
        'agent.is_subagent' => false,
    ];

    expect($counters)->toHaveCount(1)
        ->and($counters[0]->type())->toBe('counter')
        ->and($counters[0]->value())->toBe(1.0)
        ->and($counters[0]->tags()->toArray())->toBe($expectedTags);

    expect($timers)->toHaveCount(1)
        ->and($timers[0]->type())->toBe('timer')
        ->and($timers[0]->value())->toBe(15.0)
        ->and($timers[0]->tags()->toArray())->toBe($expectedTags);

    // A clock that went backwards must not take down the step it is only observing.
    [$telemetry2, $exporter2] = metricCatalogHub();
    $projector2 = new AgentsTelemetryProjector($telemetry2);
    $projector2->project(new AgentStepCompleted(
        agentId: 'agent-1', executionId: 'exec-1', parentAgentId: null, stepNumber: 1,
        hasToolCalls: true, errorCount: 0, errorMessages: '', usage: new InferenceUsage(10, 5),
        finishReason: InferenceFinishReason::ToolCalls, startedAt: new DateTimeImmutable('-1 second'), durationMs: -15.0,
    ));
    $telemetry2->flush();

    expect(metricsNamed($exporter2, 'agent.step.duration'))->toBe([]);
    expect(metricsNamed($exporter2, 'agent.step.count'))->toHaveCount(1);
});

it('emits execution count and steps histogram metrics for AgentExecutionCompleted and AgentExecutionFailed', function () {
    [$telemetry, $exporter] = metricCatalogHub();
    $projector = new AgentsTelemetryProjector($telemetry);

    $projector->project(new AgentExecutionCompleted(
        'agent-1', 'exec-1', null, ExecutionStatus::Completed, 3, new InferenceUsage(10, 5), null,
    ));
    $telemetry->flush();

    $counters = metricsNamed($exporter, 'agent.execution.count');
    $steps = metricsNamed($exporter, 'agent.execution.steps');

    expect($counters[0]->type())->toBe('counter')
        ->and($counters[0]->tags()->toArray())->toBe([
            'agent.status' => 'completed',
            'agent.is_subagent' => false,
            'agent.outcome' => 'success',
        ]);
    expect($steps[0]->type())->toBe('histogram')
        ->and($steps[0]->value())->toBe(3.0);

    [$telemetry2, $exporter2] = metricCatalogHub();
    $projector2 = new AgentsTelemetryProjector($telemetry2);
    $projector2->project(new AgentExecutionFailed(
        'agent-2', 'exec-2', null, new RuntimeException('boom'), ExecutionStatus::Failed, 1, new InferenceUsage(1, 1), 'boom',
    ));
    $telemetry2->flush();

    $failCounters = metricsNamed($exporter2, 'agent.execution.count');
    expect($failCounters[0]->tags()->toArray())->toBe([
        'agent.status' => 'failed',
        'agent.is_subagent' => false,
        'error.type' => RuntimeException::class,
        'agent.outcome' => 'failure',
    ]);
});

it('emits tool_call count and duration metrics for completed calls, and count only for blocked calls', function () {
    [$telemetry, $exporter] = metricCatalogHub();
    $projector = new AgentsTelemetryProjector($telemetry);

    $projector->project(new ToolCallCompleted(
        'agent-1', 'exec-1', null, 1, 'search', true, null,
        new DateTimeImmutable('-1 second'), new DateTimeImmutable(), 'result', 'call-1',
    ));
    $telemetry->flush();

    $counters = metricsNamed($exporter, 'agent.tool_call.count');
    $timers = metricsNamed($exporter, 'agent.tool_call.duration');

    expect($counters[0]->type())->toBe('counter')
        ->and($counters[0]->tags()->toArray())->toBe(['agent.tool' => 'search', 'agent.outcome' => 'success']);
    expect($timers)->toHaveCount(1)
        ->and($timers[0]->type())->toBe('timer');

    [$telemetry2, $exporter2] = metricCatalogHub();
    $projector2 = new AgentsTelemetryProjector($telemetry2);
    $projector2->project(new ToolCallBlocked('agent-1', 'exec-1', null, 1, 'danger', [], 'blocked by policy', 'safety-hook'));
    $telemetry2->flush();

    $blockedCounters = metricsNamed($exporter2, 'agent.tool_call.count');
    expect($blockedCounters)->toHaveCount(1)
        ->and($blockedCounters[0]->tags()->toArray())->toBe(['agent.tool' => 'danger', 'agent.outcome' => 'blocked']);
    expect(metricsNamed($exporter2, 'agent.tool_call.duration'))->toBe([]);

    // A clock that went backwards must not take down the tool call it is only observing:
    // startedAt after completedAt yields a negative computed duration_ms.
    [$telemetry3, $exporter3] = metricCatalogHub();
    $projector3 = new AgentsTelemetryProjector($telemetry3);
    $projector3->project(new ToolCallCompleted(
        'agent-1', 'exec-1', null, 1, 'search', true, null,
        new DateTimeImmutable(), new DateTimeImmutable('-1 second'), 'result', 'call-1',
    ));
    $telemetry3->flush();

    expect(metricsNamed($exporter3, 'agent.tool_call.duration'))->toBe([]);
    expect(metricsNamed($exporter3, 'agent.tool_call.count'))->toHaveCount(1);
});

it('emits a subagent depth Gauge on SubagentSpawning and a count Counter on SubagentCompleted', function () {
    [$telemetry, $exporter] = metricCatalogHub();
    $projector = new AgentsTelemetryProjector($telemetry);

    $projector->project(new SubagentSpawning('agent-1', 'helper', 'do X', 1, 3, 'exec-1', 1, 'call-1'));
    $telemetry->flush();

    $depth = metricsNamed($exporter, 'agent.subagent.depth');
    expect($depth)->toHaveCount(1)
        ->and($depth[0]->type())->toBe('gauge')
        ->and($depth[0]->value())->toBe(1.0)
        ->and($depth[0]->tags()->toArray())->toBe(['agent.subagent' => 'helper']);

    [$telemetry2, $exporter2] = metricCatalogHub();
    $projector2 = new AgentsTelemetryProjector($telemetry2);
    $projector2->project(new SubagentCompleted(
        'agent-1', 'sub-1', 'helper', ExecutionStatus::Completed, 2, new InferenceUsage(3, 2),
        new DateTimeImmutable('-2 seconds'), 'exec-1', 1, 'call-1',
    ));
    $telemetry2->flush();

    $count = metricsNamed($exporter2, 'agent.subagent.count');
    expect($count)->toHaveCount(1)
        ->and($count[0]->type())->toBe('counter')
        ->and($count[0]->tags()->toArray())->toBe(['agent.subagent' => 'helper', 'agent.subagent.status' => 'completed']);
});

// ==========================================================================
// Cross-cutting invariants (catch drift between the two projectors)
// ==========================================================================

it('never tags a metric outside the token-usage family with a per-run identifier or agent.id', function () {
    $exporter = realisticCatalogRun();

    expect($exporter->metrics)->not->toBeEmpty();

    // The single deliberate exception is inference.client.token.usage.* — see
    // MetricNames::TAG_EXECUTION_ID. Every other metric name, from either projector, must
    // stay low-cardinality: no execution id, no telemetry correlation id, no agent id.
    //
    // The exemption covers `agent.id` too, not just the execution id: the Agents projector's
    // TOKEN_USAGE_TOTAL histogram carries both — see AgentsTelemetryProjector::onTokenUsageReported.
    // That is deliberate rather than an oversight. The metric already carries
    // `inference.execution.id` for Langfuse span correlation, which makes it one series per run
    // regardless, so `agent.id` adds no series and stays useful for grouping token spend by agent.
    // Documented in docs/03-runtime-wiring.md under Tag Discipline.
    foreach ($exporter->metrics as $metric) {
        $isTokenUsage = str_starts_with($metric->name(), 'inference.client.token.usage.');
        if ($isTokenUsage) {
            continue;
        }

        $tags = $metric->tags()->toArray();
        expect($tags)->not->toHaveKey('inference.execution.id');
        expect($tags)->not->toHaveKey('telemetry.parent_operation_id');
        expect($tags)->not->toHaveKey('telemetry.root_operation_id');
        expect($tags)->not->toHaveKey('agent.id');
    }
});

it('still tags token-usage metrics with inference.execution.id so Langfuse correlation keeps working', function () {
    $exporter = realisticCatalogRun();

    $tokenUsageMetrics = array_values(array_filter(
        $exporter->metrics,
        fn(Metric $metric): bool => str_starts_with($metric->name(), 'inference.client.token.usage.'),
    ));

    expect($tokenUsageMetrics)->not->toBeEmpty();
    foreach ($tokenUsageMetrics as $metric) {
        expect($metric->tags()->toArray())->toHaveKey(MetricNames::TAG_EXECUTION_ID);
    }
});
