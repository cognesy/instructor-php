<?php

declare(strict_types=1);

namespace Cognesy\Logging\Enrichers;

use Cognesy\Events\Event;
use Cognesy\Logging\Contracts\EventEnricher;
use Cognesy\Logging\LogContext;

final readonly class TelemetryCorrelationEnricher implements EventEnricher
{
    public function __invoke(Event $event): LogContext
    {
        $payload = is_array($event->data) ? $event->data : [];

        return LogContext::fromEvent($event)
            ->withFrameworkContext($this->frameworkContext($payload))
            ->withUserContext($this->userContext($payload));
    }

    /** @param array<string, mixed> $payload */
    /** @return array<string, mixed> */
    private function frameworkContext(array $payload): array
    {
        $correlation = $this->telemetryCorrelation($payload);
        $trace = $this->traceContext($payload);

        return array_filter([
            'root_operation_id' => $correlation['root_operation_id'] ?? null,
            'parent_operation_id' => $correlation['parent_operation_id'] ?? null,
            'conversation_id' => $correlation['conversation_id'] ?? null,
            'request_id' => $correlation['request_id'] ?? $this->scalarValue($payload, 'request_id', 'requestId'),
            'session_id' => $correlation['session_id'] ?? $this->scalarValue($payload, 'session_id', 'sessionId'),
            'agent_id' => $this->scalarValue($payload, 'agent_id', 'agentId'),
            'parent_agent_id' => $this->scalarValue($payload, 'parent_agent_id', 'parentAgentId'),
            'execution_id' => $this->scalarValue($payload, 'execution_id', 'executionId'),
            'traceparent' => $trace['traceparent'] ?? null,
            'tracestate' => $trace['tracestate'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /** @param array<string, mixed> $payload */
    /** @return array<string, mixed> */
    private function userContext(array $payload): array
    {
        $correlation = $this->telemetryCorrelation($payload);

        return array_filter([
            'user_id' => $correlation['user_id'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /** @param array<string, mixed> $payload */
    /** @return array<string, mixed> */
    private function telemetryCorrelation(array $payload): array
    {
        $telemetry = $payload['telemetry'] ?? null;

        return match (true) {
            ! is_array($telemetry) => [],
            ! is_array($telemetry['correlation'] ?? null) => [],
            default => $telemetry['correlation'],
        };
    }

    /** @param array<string, mixed> $payload */
    /** @return array<string, mixed> */
    private function traceContext(array $payload): array
    {
        $telemetry = $payload['telemetry'] ?? null;
        $trace = is_array($telemetry) ? ($telemetry['trace'] ?? null) : null;
        if (is_array($trace)) {
            return $trace;
        }

        return match (true) {
            is_array($payload['trace'] ?? null) => $payload['trace'],
            default => [],
        };
    }

    /** @param array<string, mixed> $payload */
    private function scalarValue(array $payload, string ...$keys): string|int|float|bool|null
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }

            $value = $payload[$key];
            if ($value === null || is_array($value) || is_object($value)) {
                continue;
            }

            return $value;
        }

        return null;
    }
}
