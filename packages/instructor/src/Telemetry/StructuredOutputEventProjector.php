<?php declare(strict_types=1);

namespace Cognesy\Instructor\Telemetry;

use Cognesy\Events\Support\ListenerGate;
use Cognesy\Instructor\Data\ResponseFailure;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputResponse;
use Cognesy\Instructor\Events\Attempt\ResponseRecoveryExhausted;
use Cognesy\Instructor\Events\Attempt\ResponseRetryScheduled;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputRequestReceived;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseGenerated;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseUpdated;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputStarted;
use Cognesy\Instructor\Events\Support\EventValueNormalizer;
use Cognesy\Utils\Result\Result;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Single owner of structured-output event payload construction and dispatch.
 *
 * Every lifecycle event (request.received / execution.started / response.updated /
 * response.generated / response.retry_scheduled / response.recovery_exhausted) is built
 * here so that `phaseId`, `attemptId` and `valueType` have exactly one definition. The
 * sync and streaming paths previously carried byte-identical copies of these builders,
 * which drifted: attemptId was resolved active-first in one and finalized-first in the
 * other, so the same logical event correlated differently depending on the entry point.
 *
 * Payloads nobody listens for are not built. `updated()` runs once per emission, and its
 * payload costs two `strlen()` calls over the accumulated content plus a usage and tool-call
 * walk — per delta, for a value that would be discarded. Gates resolve once, in the
 * constructor: a projector is created per execution by its collaborator (stream, session,
 * runtime) at the point where that collaborator previously resolved its own gates, so
 * there is no window in which a listener could be registered and then missed.
 */
final class StructuredOutputEventProjector
{
    private readonly bool $wantsRequestReceived;
    private readonly bool $wantsStarted;
    private readonly bool $wantsUpdated;
    private readonly bool $wantsGenerated;

    public function __construct(
        private readonly EventDispatcherInterface $events,
    ) {
        $this->wantsRequestReceived = ListenerGate::wants($events, StructuredOutputRequestReceived::class);
        $this->wantsStarted = ListenerGate::wants($events, StructuredOutputStarted::class);
        $this->wantsUpdated = ListenerGate::wants($events, StructuredOutputResponseUpdated::class);
        $this->wantsGenerated = ListenerGate::wants($events, StructuredOutputResponseGenerated::class);
    }

    public function requestReceived(StructuredOutputExecution $execution): void {
        if (!$this->wantsRequestReceived) {
            return;
        }
        $this->events->dispatch(new StructuredOutputRequestReceived($this->requestReceivedPayload($execution)));
    }

    public function started(StructuredOutputExecution $execution): void {
        if (!$this->wantsStarted) {
            return;
        }
        $this->events->dispatch(new StructuredOutputStarted($this->startedPayload($execution)));
    }

    public function updated(StructuredOutputResponse $response, StructuredOutputExecution $execution): void {
        if (!$this->wantsUpdated) {
            return;
        }
        $this->events->dispatch(new StructuredOutputResponseUpdated(
            data: $this->responseMetadata($response, $execution, 'response.updated'),
            response: $response,
        ));
    }

    public function generated(StructuredOutputResponse $response, StructuredOutputExecution $execution): void {
        if (!$this->wantsGenerated) {
            return;
        }
        $this->events->dispatch(new StructuredOutputResponseGenerated(
            $this->responsePayload($response, $execution, 'response.generated'),
        ));
    }

    public function retryScheduled(StructuredOutputExecution $execution, Result $result, int $retries): void {
        $this->events->dispatch(new ResponseRetryScheduled(
            $this->recoveryPayload($execution, 'response.retry_scheduled', $retries, $result),
        ));
    }

    public function recoveryExhausted(StructuredOutputExecution $execution, Result $result, int $retries): void {
        $this->events->dispatch(new ResponseRecoveryExhausted(
            $this->recoveryPayload($execution, 'response.recovery_exhausted', $retries, $result),
        ));
    }

    /**
     * Whether the per-emission update event has any listener.
     *
     * Lets the caller skip the work it does to *produce* the arguments — resolving which
     * execution the response belongs to — not just the payload built from them.
     */
    public function wantsUpdates(): bool {
        return $this->wantsUpdated;
    }

    // INTERNAL ///////////////////////////////////////////////////////////

    private function requestReceivedPayload(StructuredOutputExecution $execution): array {
        $request = $execution->request();
        $requestedSchema = $request->requestedSchema();
        $executionId = $execution->id()->toString();

        $payload = [
            'requestId' => $request->id()->toString(),
            'executionId' => $executionId,
            'phase' => 'request.received',
            'phaseId' => $this->phaseId($executionId, 'request.received'),
            'model' => $request->model(),
            'messageCount' => count($request->messages()),
            'isStreamed' => $request->isStreamed(),
            'requestedSchemaType' => is_array($requestedSchema) ? 'array' : (is_object($requestedSchema) ? 'object' : 'string'),
        ];

        if (is_array($requestedSchema)) {
            $payload['requestedSchemaKeyCount'] = count($requestedSchema);
            return [...$payload, ...StructuredOutputTelemetry::requestReceived($execution)];
        }

        if ($requestedSchema !== '') {
            $payload['requestedSchemaClass'] = is_object($requestedSchema)
                ? $requestedSchema::class
                : ltrim($requestedSchema, '\\');
        }

        return [...$payload, ...StructuredOutputTelemetry::requestReceived($execution)];
    }

    private function startedPayload(StructuredOutputExecution $execution): array {
        $request = $execution->request();
        $executionId = $execution->id()->toString();

        return [
            'requestId' => $request->id()->toString(),
            'executionId' => $executionId,
            'phase' => 'execution.started',
            'phaseId' => $this->phaseId($executionId, 'execution.started'),
            'model' => $request->model(),
            'messageCount' => count($request->messages()),
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
        $attemptId = $this->attemptId($execution);
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

    private function recoveryPayload(
        StructuredOutputExecution $execution,
        string $phase,
        int $retries,
        Result $result,
    ): array {
        $executionId = $execution->id()->toString();
        $attemptId = $this->attemptId($execution);
        $failure = $result->error();
        $failureData = match (true) {
            $failure instanceof ResponseFailure => $failure->eventData(),
            default => [
                'errorMessage' => 'Structured output recovery failed.',
                'errorType' => get_debug_type($failure),
            ],
        };

        return [
            ...array_filter([
                'requestId' => $execution->request()->id()->toString(),
                'executionId' => $executionId,
                'attemptId' => $attemptId,
                'phase' => $phase,
                'phaseId' => $this->phaseId($executionId, $phase, $attemptId),
                'retries' => $retries,
                'errors' => [],
            ], fn(mixed $value): bool => $value !== null),
            ...$failureData,
        ];
    }

    /**
     * The single attemptId rule for every structured-output event.
     *
     * Active-first: while an attempt is in flight it is the accurate correlation target,
     * and `withFailedAttempt()`/`withSuccessfulAttempt()` both null `activeAttempt` when
     * they finalize, so terminal events fall through to the just-finalized attempt.
     * Finalized-first would mis-attribute in-flight retry events to the *previous* attempt.
     */
    private function attemptId(StructuredOutputExecution $execution): ?string {
        return $execution->activeAttempt()?->id()->toString()
            ?? $execution->lastFinalizedAttempt()?->id()->toString();
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
