<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Telemetry;

use Cognesy\Retrieval\Data\RetrievalRequest;
use Cognesy\Telemetry\Domain\Envelope\OperationCorrelation;
use Cognesy\Telemetry\Domain\Envelope\OperationDescriptor;
use Cognesy\Telemetry\Domain\Envelope\OperationKind;
use Cognesy\Telemetry\Domain\Envelope\TelemetryEnvelope;

final readonly class RetrievalTelemetry
{
    /** @return array<string, mixed> */
    public static function execution(RetrievalRequest $request, string $executionId): array
    {
        return [
            TelemetryEnvelope::KEY => (new TelemetryEnvelope(
                operation: new OperationDescriptor(
                    id: $executionId,
                    type: 'retrieval.query',
                    name: 'retrieval.query',
                    kind: OperationKind::RootSpan,
                ),
                correlation: OperationCorrelation::root(
                    operationId: $executionId,
                    sessionId: $request->id,
                    requestId: $request->id,
                ),
            ))->withTags(['retrieval', 'store'])->toArray(),
        ];
    }
}
