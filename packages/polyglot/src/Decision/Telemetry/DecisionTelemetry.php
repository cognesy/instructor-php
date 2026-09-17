<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Telemetry;

use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Telemetry\Domain\Envelope\OperationCorrelation;
use Cognesy\Telemetry\Domain\Envelope\OperationDescriptor;
use Cognesy\Telemetry\Domain\Envelope\OperationKind;
use Cognesy\Telemetry\Domain\Envelope\TelemetryEnvelope;

final readonly class DecisionTelemetry
{
    public static function execution(DecisionRequest $request, string $executionId): array
    {
        $seed = $request->telemetryCorrelation();
        $parentOperationId = $seed?->parentOperationId();
        $requestId = $request->id()->toString();
        $correlation = match ($parentOperationId) {
            null => OperationCorrelation::root(
                operationId: $executionId,
                sessionId: $seed?->sessionId() ?? $requestId,
                userId: $seed?->userId(),
                conversationId: $seed?->conversationId(),
                requestId: $requestId,
            ),
            default => OperationCorrelation::child(
                rootOperationId: $seed->rootOperationId(),
                parentOperationId: $parentOperationId,
                sessionId: $seed->sessionId(),
                userId: $seed->userId(),
                conversationId: $seed->conversationId(),
                requestId: $requestId,
            ),
        };

        return self::envelope(
            id: $executionId,
            type: 'sdm.decision',
            kind: $parentOperationId === null ? OperationKind::RootSpan : OperationKind::Span,
            correlation: $correlation,
            tags: ['sdm', 'decision'],
        );
    }

    public static function attempt(
        DecisionRequest $request,
        string $executionId,
        string $attemptId,
    ): array {
        $seed = $request->telemetryCorrelation();

        return self::envelope(
            id: $attemptId,
            type: 'sdm.decision.attempt',
            kind: OperationKind::Span,
            correlation: OperationCorrelation::child(
                rootOperationId: $seed?->rootOperationId() ?? $executionId,
                parentOperationId: $executionId,
                sessionId: $seed?->sessionId() ?? $request->id()->toString(),
                userId: $seed?->userId(),
                conversationId: $seed?->conversationId(),
                requestId: $request->id()->toString(),
            ),
            tags: ['sdm', 'decision', 'attempt'],
        );
    }

    /** @param list<string> $tags */
    private static function envelope(
        string $id,
        string $type,
        OperationKind $kind,
        OperationCorrelation $correlation,
        array $tags,
    ): array {
        return [
            TelemetryEnvelope::KEY => (new TelemetryEnvelope(
                operation: new OperationDescriptor(
                    id: $id,
                    type: $type,
                    name: $type,
                    kind: $kind,
                ),
                correlation: $correlation,
            ))->withTags($tags)->toArray(),
        ];
    }
}
