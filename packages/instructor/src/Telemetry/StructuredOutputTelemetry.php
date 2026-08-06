<?php declare(strict_types=1);

namespace Cognesy\Instructor\Telemetry;

use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputResponse;
use Cognesy\Polyglot\Telemetry\MessagesSerializationMemo;
use Cognesy\Telemetry\Domain\Envelope\CaptureMode;
use Cognesy\Telemetry\Domain\Envelope\CapturePolicy;
use Cognesy\Telemetry\Domain\Envelope\OperationCorrelation;
use Cognesy\Telemetry\Domain\Envelope\OperationDescriptor;
use Cognesy\Telemetry\Domain\Envelope\OperationIO;
use Cognesy\Telemetry\Domain\Envelope\OperationKind;
use Cognesy\Telemetry\Domain\Envelope\TelemetryEnvelope;

final readonly class StructuredOutputTelemetry
{
    public static function requestReceived(StructuredOutputExecution $execution): array
    {
        $request = $execution->request();
        $executionId = $execution->id()->toString();

        return [
            TelemetryEnvelope::KEY => (new TelemetryEnvelope(
                operation: new OperationDescriptor(
                    id: $executionId,
                    type: 'structured_output.execution',
                    name: 'structured_output.execute',
                    kind: OperationKind::RootSpan,
                ),
                correlation: OperationCorrelation::root(
                    operationId: $executionId,
                    sessionId: $request->id()->toString(),
                    requestId: $request->id()->toString(),
                ),
            ))
                ->withCapture(self::summaryCapture())
                // Memoized on conversation identity -- requestReceived(), executionStarted()
                // and responseGenerated() all serialise the same conversation, and the
                // nested inference serialises it again. See MessagesSerializationMemo.
                ->withIO(new OperationIO(input: MessagesSerializationMemo::toArray($request->messages())))
                ->withTags(['structured-output'])
                ->toArray(),
        ];
    }

    public static function executionStarted(StructuredOutputExecution $execution): array
    {
        return self::requestReceived($execution);
    }

    public static function responseGenerated(
        StructuredOutputExecution $execution,
        StructuredOutputResponse $response,
    ): array {
        $request = $execution->request();
        $executionId = $execution->id()->toString();

        return [
            TelemetryEnvelope::KEY => (new TelemetryEnvelope(
                operation: new OperationDescriptor(
                    id: $executionId,
                    type: 'structured_output.execution',
                    name: 'structured_output.execute',
                    kind: OperationKind::RootSpan,
                ),
                correlation: OperationCorrelation::root(
                    operationId: $executionId,
                    sessionId: $request->id()->toString(),
                    requestId: $request->id()->toString(),
                ),
            ))
                ->withCapture(self::summaryCapture())
                ->withIO(new OperationIO(
                    input: MessagesSerializationMemo::toArray($request->messages()),
                    output: array_filter([
                        'value' => $response->hasValue() ? $response->value() : null,
                        'value_type' => is_object($response->value()) ? $response->value()::class : get_debug_type($response->value()),
                        'finish_reason' => $response->finishReason()->value,
                    ], static fn(mixed $v): bool => $v !== null),
                ))
                ->withTags(['structured-output'])
                ->toArray(),
        ];
    }

    /**
     * The extraction child of the structured-output root.
     *
     * Built by `AttemptProcessor`, the boundary that actually holds the execution, and carried
     * down to `ExtractingBuffer` on `ExtractionInput` so the lifecycle events are stamped where
     * they are dispatched. Before this the projector reconstructed the span from bare
     * `executionId`/`phaseId` payload keys - which the buffer never wrote, so extraction spans
     * were simply never produced.
     */
    public static function extractionContext(StructuredOutputExecution $execution): PhaseTelemetryContext
    {
        return self::phaseContext(
            execution: $execution,
            phase: 'response.extraction',
            type: 'structured_output.extraction',
            name: 'structured_output.extract',
        );
    }

    /**
     * The validation child of the structured-output root.
     *
     * Same threading as extraction, one layer deeper: `ResponseMaterializer` owns the
     * validation stage and forwards this to `ResponseValidator`, which already emits a genuine
     * open/close pair - an attempt event followed by validated-or-failed. Those two events are
     * the span; no second lifecycle is introduced to describe them.
     */
    public static function validationContext(StructuredOutputExecution $execution): PhaseTelemetryContext
    {
        return self::phaseContext(
            execution: $execution,
            phase: 'response.validation',
            type: 'structured_output.validation',
            name: 'structured_output.validate',
        );
    }

    /** Same shape AttemptProcessor uses for its own phases: "{execution}:{phase}:{attempt}". */
    public static function phaseId(StructuredOutputExecution $execution, string $phase): string
    {
        $attemptId = $execution->activeAttempt()?->id()->toString() ?? 'unknown';

        return $execution->id()->toString() . ':' . $phase . ':' . $attemptId;
    }

    /**
     * Keyed on the phase id rather than the execution id: one execution retries, and each
     * attempt extracts and validates separately.
     */
    private static function phaseContext(
        StructuredOutputExecution $execution,
        string $phase,
        string $type,
        string $name,
    ): PhaseTelemetryContext {
        $executionId = $execution->id()->toString();
        $requestId = $execution->request()->id()->toString();
        $phaseId = self::phaseId($execution, $phase);

        return new PhaseTelemetryContext(
            envelope: new TelemetryEnvelope(
                operation: new OperationDescriptor(
                    id: $phaseId,
                    type: $type,
                    name: $name,
                    kind: OperationKind::Span,
                ),
                correlation: OperationCorrelation::child(
                    rootOperationId: $executionId,
                    parentOperationId: $executionId,
                    sessionId: $requestId,
                    requestId: $requestId,
                ),
            ),
            executionId: $executionId,
            phaseId: $phaseId,
            phase: $phase,
            attemptId: $execution->activeAttempt()?->id()->toString(),
        );
    }

    public static function inferenceCorrelation(StructuredOutputExecution $execution): OperationCorrelation
    {
        return OperationCorrelation::child(
            rootOperationId: $execution->id()->toString(),
            parentOperationId: $execution->id()->toString(),
            sessionId: $execution->request()->id()->toString(),
            requestId: $execution->request()->id()->toString(),
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
