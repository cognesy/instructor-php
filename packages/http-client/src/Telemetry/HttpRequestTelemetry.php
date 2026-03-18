<?php declare(strict_types=1);

namespace Cognesy\Http\Telemetry;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Telemetry\Domain\Envelope\OperationCorrelation;
use Cognesy\Telemetry\Domain\Envelope\OperationDescriptor;
use Cognesy\Telemetry\Domain\Envelope\OperationIO;
use Cognesy\Telemetry\Domain\Envelope\OperationKind;
use Cognesy\Telemetry\Domain\Envelope\TelemetryEnvelope;
use Cognesy\Telemetry\Domain\Trace\TraceContext;

final readonly class HttpRequestTelemetry
{
    public const CORRELATION_METADATA_KEY = 'telemetry.operation_correlation';

    public static function metadataForRequest(HttpRequest $request): array
    {
        return match ($request->metadata->get(self::CORRELATION_METADATA_KEY)) {
            null => [],
            default => [
                TelemetryEnvelope::KEY => self::requestEnvelope($request)->toArray(),
            ],
        };
    }

    public static function requestEnvelope(HttpRequest $request): TelemetryEnvelope
    {
        $correlation = self::correlation($request);
        $trace = self::traceContext($request);

        return (new TelemetryEnvelope(
            operation: new OperationDescriptor(
                id: $request->id,
                type: 'http.client.request',
                name: 'http.client.request',
                kind: match ($correlation->parentOperationId()) {
                    null => OperationKind::RootSpan,
                    default => OperationKind::Span,
                },
            ),
            correlation: $correlation,
            trace: $trace,
        ))->withIO(new OperationIO(
            input: $request->body()->toArray(),
        ));
    }

    public static function withCorrelation(HttpRequest $request, OperationCorrelation $correlation): HttpRequest
    {
        return $request->withMetadataKey(self::CORRELATION_METADATA_KEY, $correlation->toArray());
    }

    private static function correlation(HttpRequest $request): OperationCorrelation
    {
        $payload = $request->metadata->get(self::CORRELATION_METADATA_KEY);

        return match (true) {
            is_array($payload) && is_string($payload['root_operation_id'] ?? null) => OperationCorrelation::fromArray(
                self::correlationPayload($payload, $request),
            ),
            default => OperationCorrelation::root(operationId: $request->id, requestId: $request->id),
        };
    }

    private static function traceContext(HttpRequest $request): ?TraceContext
    {
        $value = $request->metadata->get(TraceContextMiddleware::METADATA_KEY);

        return match (true) {
            $value instanceof TraceContext => $value,
            is_array($value) && isset($value['traceparent']) => TraceContext::fromArray($value),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{
     *   root_operation_id: string,
     *   parent_operation_id?: string,
     *   session_id?: string,
     *   user_id?: string,
     *   conversation_id?: string,
     *   request_id?: string
     * }
     */
    private static function correlationPayload(array $payload, HttpRequest $request): array
    {
        $data = [
            'root_operation_id' => (string) $payload['root_operation_id'],
            'request_id' => $request->id,
        ];

        $data = match (is_string($payload['parent_operation_id'] ?? null)) {
            true => [...$data, 'parent_operation_id' => $payload['parent_operation_id']],
            false => $data,
        };
        $data = match (is_string($payload['session_id'] ?? null)) {
            true => [...$data, 'session_id' => $payload['session_id']],
            false => $data,
        };
        $data = match (is_string($payload['user_id'] ?? null)) {
            true => [...$data, 'user_id' => $payload['user_id']],
            false => $data,
        };

        return match (is_string($payload['conversation_id'] ?? null)) {
            true => [...$data, 'conversation_id' => $payload['conversation_id']],
            false => $data,
        };
    }
}
