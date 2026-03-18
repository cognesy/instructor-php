<?php declare(strict_types=1);

namespace Cognesy\Instructor\Telemetry;

use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputResponse;
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
                ->withIO(new OperationIO(input: $request->messages()->toArray()))
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
                    input: $request->messages()->toArray(),
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
