<?php

declare(strict_types=1);

namespace Cognesy\Logging\Observability;

use Cognesy\Events\Event;
use Cognesy\Logging\Contracts\EventFormatter;
use Cognesy\Logging\LogContext;
use Cognesy\Logging\LogEntry;

/**
 * Normalizes an Event into a structured LogEntry with consistent correlation fields.
 *
 * Guaranteed context fields:
 *   - event_id, event_class, event_name
 *   - package, component (derived from dispatcher name)
 *   - payload (full $event->data)
 *   - correlation (per-package identifiers extracted from payload)
 */
final class StructuredEventFormatter implements EventFormatter
{
    public function __construct(
        private string $component = '',
        private bool $includePayload = true,
        private bool $includeCorrelation = true,
        private bool $includeEventMetadata = true,
        private bool $includeComponentMetadata = true,
        private int $stringClipLength = 0,
    ) {}

    public function __invoke(Event $event, LogContext $context): LogEntry
    {
        $payload = is_array($event->data) ? $event->data : ['value' => $event->data];
        $eventClass = get_class($event);
        $logContext = [];

        if ($this->includeEventMetadata) {
            $logContext['event_id'] = $event->id;
            $logContext['event_class'] = $eventClass;
            $logContext['event_name'] = $event->name();
        }

        if ($this->includeComponentMetadata) {
            $logContext['package'] = $this->resolvePackage();
            $logContext['component'] = $this->component;
        }

        if ($this->includeCorrelation) {
            $logContext['correlation'] = $this->clipValue($this->extractCorrelation($payload));
        }

        if ($this->includePayload) {
            $logContext['payload'] = $this->clipValue($payload);
        }

        return LogEntry::create(
            level:     $event->logLevel,
            message:   $event->name(),
            channel:   $this->component ?: 'instructor',
            context:   $logContext,
            timestamp: $event->createdAt,
        );
    }

    private function resolvePackage(): string
    {
        if ($this->component === '') {
            return '';
        }
        // e.g. "instructor.structured-output.runtime" → "instructor"
        $parts = explode('.', $this->component, 2);
        return $parts[0];
    }

    /**
     * Extracts well-known correlation identifiers from the event payload.
     * Unknown keys are silently ignored — no exceptions.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function extractCorrelation(array $payload): array
    {
        return [
            ...$this->telemetryCorrelation($payload),
            ...$this->payloadCorrelation($payload),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function telemetryCorrelation(array $payload): array
    {
        $telemetry = $payload['telemetry'] ?? null;
        if (!is_array($telemetry)) {
            return [];
        }

        $correlation = $telemetry['correlation'] ?? null;
        if (!is_array($correlation)) {
            return [];
        }

        $result = [];
        foreach ($correlation as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            if ($value === null || $value === '') {
                continue;
            }
            $result[$key] = $value;
        }
        return $result;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function payloadCorrelation(array $payload): array
    {
        $aliases = [
            'request_id' => ['request_id', 'requestId'],
            'execution_id' => ['execution_id', 'executionId'],
            'attempt_id' => ['attempt_id', 'attemptId'],
            'phase_id' => ['phase_id', 'phaseId'],
            'agent_id' => ['agent_id', 'agentId'],
            'parent_agent_id' => ['parent_agent_id', 'parentAgentId'],
            'session_id' => ['session_id', 'sessionId'],
            'agent_type' => ['agent_type', 'agentType'],
            'status' => ['status', 'statusCode'],
            'duration_ms' => ['duration_ms', 'durationMs'],
            'bytes' => ['bytes'],
            'url' => ['url'],
            'method' => ['method'],
            'provider' => ['provider'],
            'model' => ['model'],
            'step' => ['step'],
        ];

        $result = [];
        foreach ($aliases as $normalized => $keys) {
            foreach ($keys as $key) {
                if (!array_key_exists($key, $payload)) {
                    continue;
                }
                if ($payload[$key] === null || $payload[$key] === '') {
                    continue;
                }
                $result[$normalized] = $payload[$key];
                continue 2;
            }
        }
        return $result;
    }

    private function clipValue(mixed $value): mixed
    {
        if ($this->stringClipLength <= 0) {
            return $value;
        }

        return match (true) {
            is_string($value) => mb_substr($value, 0, $this->stringClipLength),
            is_array($value) => array_map($this->clipValue(...), $value),
            default => $value,
        };
    }
}
