<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Domain\Metric;

/**
 * Canonical names and tag keys for metrics emitted by more than one runtime package.
 *
 * `inference.client.token.usage.*` is produced by both PolyglotTelemetryProjector and
 * AgentsTelemetryProjector, and matched by name in LangfusePayloadMapper. Three copies
 * of the same literal in three packages is exactly how naming drifts, so the literal
 * lives here instead. Metric names used by a single producer (`agent.*`,
 * `inference.embeddings.*`) stay private to that producer.
 *
 * These live in telemetry because it is the only package shared by every producer and
 * consumer. The metric *types* remain owned by packages/metrics — this class holds no
 * behaviour, only the vocabulary.
 */
final class MetricNames
{
    public const TOKEN_USAGE_INPUT = 'inference.client.token.usage.input';
    public const TOKEN_USAGE_OUTPUT = 'inference.client.token.usage.output';
    public const TOKEN_USAGE_TOTAL = 'inference.client.token.usage.total';

    /**
     * Tag carried on token-usage metrics so exporters can correlate a metric back to the
     * span it belongs to — see LangfusePayloadMapper::matchesMetric(). Deliberately
     * high-cardinality: it is an identifier, not a dimension.
     *
     * This makes the token-usage family correlation-carrying rather than
     * aggregation-friendly: it is already one time series per run, and the agents projector
     * additionally tags it with `agent.id`. Metrics added for aggregation must carry neither.
     */
    public const TAG_EXECUTION_ID = 'inference.execution.id';

    /** Tag marking the final (non-incremental) usage report for an execution. */
    public const TAG_USAGE_FINAL = 'inference.usage.final';

    private function __construct() {}
}
