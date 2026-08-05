<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Telemetry;

use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
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
            default => self::childOf(
                request: $request,
                seed: $seed,
                rootOperationId: $rootOperationId,
                parentOperationId: $parentOperationId,
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
                    // Memoized on conversation identity: the four envelope-building sites
                    // see the same Messages instance on a non-retried request.
                    input: MessagesSerializationMemo::toArray($request->messages()),
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
                correlation: self::childOfExecution($execution),
            ))
                ->withCapture(self::summaryCapture())
                ->withIO(new OperationIO(
                    // Memoized on conversation identity: the four envelope-building sites
                    // see the same Messages instance on a non-retried request.
                    input: MessagesSerializationMemo::toArray($request->messages()),
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
                correlation: self::childOfExecution($execution),
            ))->withTags(['llm', 'usage'])->toArray(),
        ];
    }

    /**
     * The `telemetryCorrelation()?->x() ?? fallback` ladder, written once.
     *
     * `attempt()` and `usage()` both hang their span off the execution, so they share
     * this shape exactly; `execution()` differs only in supplying an explicit seed and
     * root/parent pair, which is why it calls childOf() directly.
     */
    private static function childOfExecution(InferenceExecution $execution): OperationCorrelation
    {
        $request = $execution->request();

        return self::childOf(
            request: $request,
            seed: $request->telemetryCorrelation(),
            rootOperationId: $request->telemetryCorrelation()?->rootOperationId() ?? $execution->id->toString(),
            parentOperationId: $execution->id->toString(),
        );
    }

    private static function childOf(
        InferenceRequest $request,
        ?OperationCorrelation $seed,
        string $rootOperationId,
        string $parentOperationId,
    ): OperationCorrelation {
        $requestId = $request->id()->toString();

        return OperationCorrelation::child(
            rootOperationId: $rootOperationId,
            parentOperationId: $parentOperationId,
            sessionId: $seed?->sessionId() ?? $requestId,
            userId: $seed?->userId(),
            conversationId: $seed?->conversationId(),
            requestId: $requestId,
        );
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
