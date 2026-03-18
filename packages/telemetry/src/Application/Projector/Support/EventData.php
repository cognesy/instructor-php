<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Application\Projector\Support;

use Cognesy\Events\Event;
use Cognesy\Telemetry\Domain\Envelope\TelemetryEnvelope;

final readonly class EventData
{
    /** @return array<string, mixed> */
    public static function of(object $event): array
    {
        return match (true) {
            $event instanceof Event && is_array($event->data) => $event->data,
            default => get_object_vars($event),
        };
    }

    public static function string(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return match (true) {
            is_string($value) && $value !== '' => $value,
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value), is_float($value) => (string) $value,
            default => null,
        };
    }

    public static function int(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return match (true) {
            is_int($value) => $value,
            is_numeric($value) => (int) $value,
            default => null,
        };
    }

    public static function float(array $data, string $key): ?float
    {
        $value = $data[$key] ?? null;

        return match (true) {
            is_float($value), is_int($value) => (float) $value,
            is_numeric($value) => (float) $value,
            default => null,
        };
    }

    public static function bool(array $data, string $key): ?bool
    {
        $value = $data[$key] ?? null;

        return match (true) {
            is_bool($value) => $value,
            default => null,
        };
    }

    /** @return array<string, mixed> */
    public static function array(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        return match (true) {
            is_array($value) => $value,
            default => [],
        };
    }

    public static function telemetry(array $data): ?TelemetryEnvelope
    {
        $value = $data[TelemetryEnvelope::KEY] ?? null;

        return match (true) {
            self::isTelemetryEnvelope($value) => TelemetryEnvelope::fromArray($value),
            default => null,
        };
    }

    /**
     * @phpstan-assert-if-true array{
     *   operation: array{id: string, type: string, name: string, kind: string},
     *   correlation: array{root_operation_id: string, parent_operation_id?: string, session_id?: string, user_id?: string, conversation_id?: string, request_id?: string},
     *   trace?: array{traceparent: string, tracestate?: string},
     *   capture?: array{input?: string, output?: string, metadata?: string},
     *   io?: array{input?: mixed, output?: mixed},
     *   tags?: list<string>,
     *   metadata?: array<string, mixed>
     * } $value
     */
    private static function isTelemetryEnvelope(mixed $value): bool
    {
        return match (true) {
            !is_array($value) => false,
            !isset($value['operation'], $value['correlation']) => false,
            !is_array($value['operation']), !is_array($value['correlation']) => false,
            default => true,
        };
    }
}
