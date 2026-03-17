<?php declare(strict_types=1);

namespace Cognesy\Instructor\Core;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Instructor\Contracts\CanEmitStreamingUpdates;
use Cognesy\Instructor\Creation\ExecutionDriverFactory;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputResponse;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseGenerated;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputStarted;
use Cognesy\Instructor\StructuredOutputStream;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use RuntimeException;

final class StructuredOutputExecutionSession
{
    private StructuredOutputExecution $execution;
    private ?CanEmitStreamingUpdates $executionDriver = null;
    private ?StructuredOutputStream $cachedStream = null;

    public function __construct(
        StructuredOutputExecution $execution,
        private readonly ExecutionDriverFactory $executionDriverFactory,
        private readonly CanHandleEvents $events,
    ) {
        $this->execution = $execution;
    }

    public function output(): mixed
    {
        if ($this->execution->hasOutput()) {
            return $this->execution->output();
        }

        $this->inferenceResponse();

        return $this->execution->output();
    }

    public function inferenceResponse(): InferenceResponse
    {
        $existingResponse = $this->execution->inferenceResponse();
        if ($existingResponse !== null) {
            return $existingResponse;
        }

        if ($this->execution->isStreamed() || $this->cachedStream !== null) {
            return $this->stream()->finalInferenceResponse();
        }

        $this->events->dispatch(new StructuredOutputStarted($this->startedPayload($this->execution)));

        $driver = $this->executionDriver();
        while ($driver->hasNextEmission()) {
            $driver->nextEmission();
        }
        $this->execution = $driver->execution();

        $response = $this->execution->inferenceResponse();
        if ($response === null) {
            throw new RuntimeException('Failed to get inference response');
        }

        $this->events->dispatch(new StructuredOutputResponseGenerated($this->responsePayload(
            execution: $this->execution,
            response: StructuredOutputResponse::final(
                value: $this->execution->output(),
                inferenceResponse: $response,
            ),
            phase: 'response.generated',
        )));

        return $response;
    }

    public function stream(): StructuredOutputStream
    {
        if ($this->cachedStream !== null) {
            return $this->cachedStream;
        }

        $this->execution = $this->execution->withStreamed();
        $this->cachedStream = new StructuredOutputStream(
            $this->execution,
            $this->executionDriverFactory->makeStreamingExecutionDriver($this->execution),
            $this->events,
        );

        return $this->cachedStream;
    }

    public function execution(): StructuredOutputExecution
    {
        return $this->execution;
    }

    private function executionDriver(): CanEmitStreamingUpdates
    {
        if ($this->executionDriver !== null) {
            return $this->executionDriver;
        }

        $this->executionDriver = $this->executionDriverFactory->makeExecutionDriver($this->execution);

        return $this->executionDriver;
    }

    private function startedPayload(StructuredOutputExecution $execution) : array
    {
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
        ];
    }

    private function responsePayload(
        StructuredOutputExecution $execution,
        StructuredOutputResponse $response,
        string $phase,
    ) : array {
        $request = $execution->request();
        $executionId = $execution->id()->toString();
        $attemptId = $execution->lastFinalizedAttempt()?->id()->toString()
            ?? $execution->activeAttempt()?->id()->toString();
        $usage = $response->usage();

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
            'hasToolCalls' => !$response->toolCalls()->isEmpty(),
            'toolCallCount' => $response->toolCalls()->count(),
            'inputTokens' => $usage->input(),
            'outputTokens' => $usage->output(),
            'cacheWriteTokens' => $usage->cacheWriteTokens,
            'cacheReadTokens' => $usage->cacheReadTokens,
            'reasoningTokens' => $usage->reasoningTokens,
            'totalTokens' => $usage->total(),
        ], fn(mixed $value): bool => $value !== null);
    }

    private function phaseId(string $executionId, string $phase, ?string $attemptId = null) : string
    {
        return match ($attemptId) {
            null => "{$executionId}:{$phase}",
            default => "{$executionId}:{$phase}:{$attemptId}",
        };
    }

    private function valueType(mixed $value) : string
    {
        return is_object($value) ? $value::class : get_debug_type($value);
    }
}
