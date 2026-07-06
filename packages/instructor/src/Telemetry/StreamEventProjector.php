<?php declare(strict_types=1);

namespace Cognesy\Instructor\Telemetry;

use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputResponse;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseGenerated;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseUpdated;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputStarted;
use Cognesy\Instructor\Events\Support\EventValueNormalizer;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Builds and dispatches the stream-level StructuredOutput events
 * (started / response.updated / response.generated). Payload construction
 * is observability concern — extracted from StructuredOutputStream so the
 * stream class owns only consumption semantics.
 */
final class StreamEventProjector
{
    public function __construct(
        private readonly EventDispatcherInterface $events,
    ) {}

    public function started(StructuredOutputExecution $execution): void {
        $this->events->dispatch(new StructuredOutputStarted($this->startedPayload($execution)));
    }

    public function updated(StructuredOutputResponse $response, StructuredOutputExecution $execution): void {
        $this->events->dispatch(new StructuredOutputResponseUpdated(
            data: $this->responseMetadata($response, $execution, 'response.updated'),
            response: $response,
        ));
    }

    public function generated(StructuredOutputResponse $response, StructuredOutputExecution $execution): void {
        $this->events->dispatch(new StructuredOutputResponseGenerated(
            $this->responsePayload($response, $execution, 'response.generated'),
        ));
    }

    // INTERNAL ///////////////////////////////////////////////////////////

    private function startedPayload(StructuredOutputExecution $execution): array {
        $request = $execution->request();
        $executionId = $execution->id()->toString();

        return [
            'requestId' => $request->id()->toString(),
            'executionId' => $executionId,
            'phase' => 'execution.started',
            'phaseId' => $this->phaseId($executionId, 'execution.started'),
            'model' => $request->model(),
            'messageCount' => count($request->messages()->toArray()),
            'isStreamed' => $request->isStreamed(),
            'attemptCount' => $execution->attemptCount(),
            ...StructuredOutputTelemetry::executionStarted($execution),
        ];
    }

    private function responsePayload(
        StructuredOutputResponse $response,
        StructuredOutputExecution $execution,
        string $phase,
    ): array {
        return array_filter([
            ...$this->responseMetadata($response, $execution, $phase),
            'value' => EventValueNormalizer::normalize($response->value()),
            'content' => $response->content(),
            'reasoningContent' => $response->reasoningContent(),
            'toolArgsSnapshot' => $response->toolArgsSnapshot(),
            'toolCalls' => $response->toolCalls()->toArray(),
            ...StructuredOutputTelemetry::responseGenerated($execution, $response),
        ], fn(mixed $value): bool => $value !== null);
    }

    private function responseMetadata(
        StructuredOutputResponse $response,
        StructuredOutputExecution $execution,
        string $phase,
    ): array {
        $request = $execution->request();
        $executionId = $execution->id()->toString();
        $attemptId = $execution->activeAttempt()?->id()->toString()
            ?? $execution->lastFinalizedAttempt()?->id()->toString();
        $usage = $response->usage();
        $toolCalls = $response->toolCalls();

        return array_filter([
            'requestId' => $request->id()->toString(),
            'executionId' => $executionId,
            'attemptId' => $attemptId,
            'phase' => $phase,
            'phaseId' => $this->phaseId($executionId, $phase, $attemptId),
            'isPartial' => $response->isPartial(),
            'hasValue' => $response->hasValue(),
            'valueType' => $this->valueType($response->value()),
            'finishReason' => $response->finishReason()->value,
            'contentLength' => strlen($response->content()),
            'reasoningContentLength' => strlen($response->reasoningContent()),
            'hasToolCalls' => !$toolCalls->isEmpty(),
            'toolCallCount' => $toolCalls->count(),
            'inputTokens' => $usage->input(),
            'outputTokens' => $usage->output(),
            'cacheWriteTokens' => $usage->cacheWriteTokens,
            'cacheReadTokens' => $usage->cacheReadTokens,
            'reasoningTokens' => $usage->reasoningTokens,
            'totalTokens' => $usage->total(),
        ], fn(mixed $value): bool => $value !== null);
    }

    private function phaseId(string $executionId, string $phase, ?string $attemptId = null): string {
        return match ($attemptId) {
            null => "{$executionId}:{$phase}",
            default => "{$executionId}:{$phase}:{$attemptId}",
        };
    }

    private function valueType(mixed $value): string {
        return is_object($value) ? $value::class : get_debug_type($value);
    }
}
