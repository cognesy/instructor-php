<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Core;

use Cognesy\Events\Support\ListenerGate;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptFailed;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptStarted;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptSucceeded;
use Cognesy\Polyglot\Inference\Events\InferenceCompleted;
use Cognesy\Polyglot\Inference\Events\InferenceStarted;
use Cognesy\Polyglot\Inference\Events\InferenceUsageReported;
use Cognesy\Polyglot\Inference\Exceptions\ProviderException;
use Cognesy\Polyglot\Telemetry\InferenceTelemetry;
use Cognesy\Telemetry\Domain\Envelope\OperationCorrelation;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Every lifecycle event an inference execution emits, and the timing behind them.
 *
 * Extracted from InferenceExecutionSession so that the session is left with the job its
 * name describes -- driving a request to a response -- and so that the six listener gates
 * are resolved in one place, once, in a constructor that runs once per execution.
 *
 * ONE INSTANCE PER EXECUTION. Never per attempt, never per delta. The gates and both
 * stopwatches are per-execution state, and constructing this on the delta path would put
 * six hasListenersFor() calls behind every chunk.
 *
 * The emitter is deliberately NOT told about retries, caching, or streams. It is told what
 * happened and emits the corresponding event. The one exception is beginAttempt(), which
 * also advances the execution -- see the note on that method.
 */
final class InferenceLifecycleEmitter
{
    private readonly MonotonicStopwatch $executionStopwatch;
    private readonly MonotonicStopwatch $attemptStopwatch;

    private int $attemptNumber = 0;

    /**
     * Whether anything consumes each lifecycle event. Every one of these carries an
     * InferenceTelemetry envelope, and four of the six build sites serialise the entire
     * conversation via Messages::toArray() -- measured at 3.2us / 30us / 115us for a
     * short / medium / long conversation, so up to ~480us per request that nobody reads.
     *
     * Resolved once per execution; see ListenerGate for the fail-open contract and for why
     * this is not checked at the dispatch site. Listeners registered after this emitter
     * was constructed are not observed by it. Wiretaps are safe: hasListenersFor()
     * reports true whenever a '*' listener exists.
     */
    private readonly bool $emitStarted;
    private readonly bool $emitAttemptStarted;
    private readonly bool $emitAttemptSucceeded;
    private readonly bool $emitUsageReported;
    private readonly bool $emitAttemptFailed;
    private readonly bool $emitCompleted;

    /**
     * @param (callable(): int)|null $monotonicNanoReader Injectable monotonic clock
     *        (nanoseconds) used only by tests to simulate long durations; production
     *        defaults to hrtime.
     */
    public function __construct(
        private readonly EventDispatcherInterface $events,
        private readonly ?OperationCorrelation $executionCorrelation = null,
        ?callable $monotonicNanoReader = null,
    ) {
        $this->executionStopwatch = new MonotonicStopwatch($monotonicNanoReader);
        $this->attemptStopwatch = new MonotonicStopwatch($monotonicNanoReader);

        $wants = ListenerGate::wantsAny($events, [
            InferenceStarted::class,
            InferenceAttemptStarted::class,
            InferenceAttemptSucceeded::class,
            InferenceUsageReported::class,
            InferenceAttemptFailed::class,
            InferenceCompleted::class,
        ]);
        $this->emitStarted = $wants[InferenceStarted::class];
        $this->emitAttemptStarted = $wants[InferenceAttemptStarted::class];
        $this->emitAttemptSucceeded = $wants[InferenceAttemptSucceeded::class];
        $this->emitUsageReported = $wants[InferenceUsageReported::class];
        $this->emitAttemptFailed = $wants[InferenceAttemptFailed::class];
        $this->emitCompleted = $wants[InferenceCompleted::class];
    }

    public function attemptNumber(): int {
        return $this->attemptNumber;
    }

    /**
     * Idempotent: the first call starts the execution clock and emits InferenceStarted,
     * every later call is a no-op. Both entry points into the session (stream() and
     * response()) call it unconditionally and rely on that.
     */
    public function executionStarted(InferenceExecution $execution): void {
        if ($this->executionStopwatch->isRunning()) {
            return;
        }

        $this->executionStopwatch->start();

        // The stopwatch must start regardless -- durationMs is read by callers that never
        // see this event. Only the payload and the dispatch are conditional.
        if (!$this->emitStarted) {
            return;
        }

        $this->events->dispatch(InferenceStarted::fromLifecycle(
            executionId: $execution->id->toString(),
            requestId: $execution->request()->id()->toString(),
            isStreamed: $execution->request()->isStreamed(),
            model: $execution->request()->model(),
            messageCount: count($execution->request()->messages()),
            data: InferenceTelemetry::execution($execution, $this->executionCorrelation),
        ));
    }

    /**
     * Opens an attempt and returns the advanced execution.
     *
     * This is the one method that both mutates and emits, and it is deliberate. An
     * attempt's identity is a single thing -- its number, its id, its telemetry
     * correlation and its start time -- and every part of it is read by the event this
     * method dispatches. Splitting the counter and the stopwatch onto the emitter while
     * leaving startAttempt() and the correlation stamp on the session is precisely the
     * arrangement that lets an attemptId drift out of step with its attemptNumber, which
     * is the defect this decomposition exists to make impossible.
     */
    public function beginAttempt(InferenceExecution $execution): InferenceExecution {
        $this->attemptNumber++;
        $this->attemptStopwatch->start();

        $execution = $execution->startAttempt();
        $execution = $execution->withRequest(
            $execution->request()->withTelemetryCorrelation($this->correlationForAttempt($execution)),
        );

        // Attempt bookkeeping above is state, not telemetry -- it always runs.
        if (!$this->emitAttemptStarted) {
            return $execution;
        }

        $this->events->dispatch(new InferenceAttemptStarted(
            executionId: $execution->id->toString(),
            attemptId: self::attemptId($execution),
            attemptNumber: $this->attemptNumber,
            model: $execution->request()->model(),
            data: InferenceTelemetry::attempt($execution),
        ));

        return $execution;
    }

    public function attemptSucceeded(InferenceExecution $execution, InferenceResponse $response): void {
        $usage = $response->usage();
        // Outside the guards on purpose: this throws if no attempt was started, and an
        // invariant that only fires when someone is listening is worse than no invariant.
        $attemptId = self::attemptId($execution);

        if ($this->emitAttemptSucceeded) {
            $this->events->dispatch(InferenceAttemptSucceeded::fromLifecycle(
                executionId: $execution->id->toString(),
                attemptId: $attemptId,
                attemptNumber: $this->attemptNumber,
                finishReason: $response->finishReason()->value,
                durationMs: $this->attemptStopwatch->elapsedMs(),
                usage: $usage,
                data: InferenceTelemetry::attempt($execution),
            ));
        }

        if ($this->emitUsageReported) {
            $this->events->dispatch(InferenceUsageReported::fromLifecycle(
                executionId: $execution->id->toString(),
                model: $execution->request()->model(),
                isFinal: true,
                usage: $usage,
                data: InferenceTelemetry::usage($execution),
            ));
        }
    }

    public function attemptFailed(
        InferenceExecution $execution,
        \Throwable $error,
        InferenceUsage $partialUsage,
        bool $willRetry,
    ): void {
        // See attemptSucceeded(): kept outside the guard so the invariant holds
        // whether or not anyone is listening.
        $attemptId = self::attemptId($execution);

        if (!$this->emitAttemptFailed) {
            return;
        }

        // Guarded: this is the only path that also runs redaction over the error message.
        $this->events->dispatch(InferenceAttemptFailed::fromLifecycle(
            executionId: $execution->id->toString(),
            attemptId: $attemptId,
            attemptNumber: $this->attemptNumber,
            errorMessage: SensitiveDataRedactor::redactMessage($error->getMessage()),
            errorType: get_class($error),
            httpStatusCode: self::statusCodeOf($error),
            willRetry: $willRetry,
            durationMs: $this->attemptStopwatch->elapsedMs(),
            data: self::partialUsageData($partialUsage) + InferenceTelemetry::attempt($execution),
        ));
    }

    public function executionCompleted(InferenceExecution $execution, bool $isSuccess): void {
        if (!$this->emitCompleted) {
            return;
        }

        $response = $execution->response();
        $usage = $response?->usage() ?? $execution->usage();
        $finishReason = $response?->finishReason() ?? InferenceFinishReason::Error;

        $this->events->dispatch(InferenceCompleted::fromLifecycle(
            executionId: $execution->id->toString(),
            isSuccess: $isSuccess,
            finishReason: $finishReason->value,
            durationMs: $this->executionStopwatch->elapsedMs(),
            attemptCount: $this->attemptNumber,
            usage: $usage,
            data: InferenceTelemetry::execution($execution, $this->executionCorrelation),
        ));
    }

    private function correlationForAttempt(InferenceExecution $execution): OperationCorrelation {
        $request = $execution->request();
        $correlation = $request->telemetryCorrelation();

        return match ($correlation) {
            null => OperationCorrelation::child(
                rootOperationId: $execution->id->toString(),
                parentOperationId: self::attemptId($execution),
                requestId: $request->id()->toString(),
            ),
            default => OperationCorrelation::child(
                rootOperationId: $correlation->rootOperationId(),
                parentOperationId: self::attemptId($execution),
                sessionId: $correlation->sessionId(),
                userId: $correlation->userId(),
                conversationId: $correlation->conversationId(),
                requestId: $request->id()->toString(),
            ),
        };
    }

    private static function attemptId(InferenceExecution $execution): string {
        $currentAttempt = $execution->currentAttempt();
        if ($currentAttempt === null) {
            throw new \LogicException('Attempt not started before event dispatch.');
        }

        return $currentAttempt->id->toString();
    }

    /**
     * @return array<string,int>
     */
    private static function partialUsageData(InferenceUsage $partialUsage): array {
        return [
            'partialInputTokens' => $partialUsage->inputTokens,
            'partialOutputTokens' => $partialUsage->outputTokens,
            'partialCacheWriteTokens' => $partialUsage->cacheWriteTokens,
            'partialCacheReadTokens' => $partialUsage->cacheReadTokens,
            'partialReasoningTokens' => $partialUsage->reasoningTokens,
            'partialTotalTokens' => $partialUsage->total(),
        ];
    }

    private static function statusCodeOf(\Throwable $error): ?int {
        return match (true) {
            $error instanceof HttpRequestException => $error->getStatusCode(),
            $error instanceof ProviderException => $error->statusCode,
            default => null,
        };
    }
}
