<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Telemetry;

use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Telemetry\Domain\Envelope\CaptureMode;
use Cognesy\Telemetry\Domain\Envelope\CapturePolicy;
use Cognesy\Telemetry\Domain\Envelope\OperationCorrelation;
use Cognesy\Telemetry\Domain\Envelope\OperationDescriptor;
use Cognesy\Telemetry\Domain\Envelope\OperationIO;
use Cognesy\Telemetry\Domain\Envelope\OperationKind;
use Cognesy\Telemetry\Domain\Envelope\TelemetryEnvelope;

final readonly class InferenceTelemetry
{
    public static function execution(
        InferenceExecution $execution,
        ?OperationCorrelation $correlationSeed = null,
    ): array
    {
        $request = $execution->request();
        $executionId = $execution->id->toString();
        $seed = $correlationSeed ?? $request->telemetryCorrelation();
        $parentOperationId = $seed?->parentOperationId();
        $rootOperationId = $seed?->rootOperationId() ?? $executionId;

        $correlation = match ($parentOperationId) {
            null => OperationCorrelation::root(
                operationId: $executionId,
                sessionId: $request->id()->toString(),
                requestId: $request->id()->toString(),
            ),
            default => OperationCorrelation::child(
                rootOperationId: $rootOperationId,
                parentOperationId: $parentOperationId,
                sessionId: $seed?->sessionId() ?? $request->id()->toString(),
                userId: $seed?->userId(),
                conversationId: $seed?->conversationId(),
                requestId: $request->id()->toString(),
            ),
        };
        $kind = match ($parentOperationId) {
            null => OperationKind::RootSpan,
            default => OperationKind::Span,
        };

        $response = $execution->response();

        return [
            TelemetryEnvelope::KEY => (new TelemetryEnvelope(
                operation: new OperationDescriptor(
                    id: $executionId,
                    type: 'llm.inference',
                    name: 'llm.inference',
                    kind: $kind,
                ),
                correlation: $correlation,
            ))
                ->withCapture(self::summaryCapture())
                ->withIO(new OperationIO(
                    input: $request->messages()->toArray(),
                    output: $response !== null ? array_filter([
                        'content' => $response->content(),
                        'finish_reason' => $response->finishReason()->value,
                        'tool_calls' => $response->hasToolCalls() ? $response->toolCalls()->toArray() : null,
                    ], static fn(mixed $v): bool => $v !== null && $v !== '') : null,
                ))
                ->withTags(['llm', 'inference'])
                ->toArray(),
        ];
    }

    public static function attempt(InferenceExecution $execution): array
    {
        $request = $execution->request();
        $attemptId = $execution->currentAttempt()?->id->toString() ?? '';
        $response = $execution->currentAttempt()?->response();

        return [
            TelemetryEnvelope::KEY => (new TelemetryEnvelope(
                operation: new OperationDescriptor(
                    id: $attemptId,
                    type: 'llm.inference.attempt',
                    name: 'llm.inference.attempt',
                    kind: OperationKind::Span,
                ),
                correlation: OperationCorrelation::child(
                    rootOperationId: $request->telemetryCorrelation()?->rootOperationId() ?? $execution->id->toString(),
                    parentOperationId: $execution->id->toString(),
                    sessionId: $request->telemetryCorrelation()?->sessionId() ?? $request->id()->toString(),
                    userId: $request->telemetryCorrelation()?->userId(),
                    conversationId: $request->telemetryCorrelation()?->conversationId(),
                    requestId: $request->id()->toString(),
                ),
            ))
                ->withCapture(self::summaryCapture())
                ->withIO(new OperationIO(
                    input: $request->messages()->toArray(),
                    output: $response !== null ? array_filter([
                        'content' => $response->content(),
                        'finish_reason' => $response->finishReason()->value,
                        'tool_calls' => $response->hasToolCalls() ? $response->toolCalls()->toArray() : null,
                    ], static fn(mixed $v): bool => $v !== null && $v !== '') : null,
                ))
                ->withTags(['llm', 'attempt'])
                ->toArray(),
        ];
    }

    public static function usage(InferenceExecution $execution): array
    {
        return [
            TelemetryEnvelope::KEY => (new TelemetryEnvelope(
                operation: new OperationDescriptor(
                    id: $execution->id->toString() . ':usage',
                    type: 'inference.usage',
                    name: 'inference.usage',
                    kind: OperationKind::Metric,
                ),
                correlation: OperationCorrelation::child(
                    rootOperationId: $execution->request()->telemetryCorrelation()?->rootOperationId() ?? $execution->id->toString(),
                    parentOperationId: $execution->id->toString(),
                    sessionId: $execution->request()->telemetryCorrelation()?->sessionId() ?? $execution->request()->id()->toString(),
                    userId: $execution->request()->telemetryCorrelation()?->userId(),
                    conversationId: $execution->request()->telemetryCorrelation()?->conversationId(),
                    requestId: $execution->request()->id()->toString(),
                ),
            ))->withTags(['llm', 'usage'])->toArray(),
        ];
    }

    private static function summaryCapture(): CapturePolicy
    {
        return new CapturePolicy(
            input: CaptureMode::Summary,
            output: CaptureMode::Summary,
            metadata: CaptureMode::Summary,
        );
    }
}
