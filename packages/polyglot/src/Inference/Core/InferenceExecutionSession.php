<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Core;

use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Polyglot\Inference\Enums\InferenceFailureAction;
use Cognesy\Polyglot\Inference\Streaming\InferenceStream;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Drives one inference request to one response, retrying as policy allows, and presents the
 * result as either a stream or a single response.
 *
 * The lifecycle events and the retry decisions each live in their own collaborator, both built
 * here and both per execution -- never per attempt, never per delta. What is left is this
 * class's actual job: owning the execution, and reconciling the two ways a caller can ask for
 * its result.
 *
 * THERE IS NO RESPONSE CACHE, and the reason is worth stating so it is not helpfully re-added.
 * A per-execution one existed and could never return a value: response() checks
 * $this->execution->response() first, and the cache was only ever written from succeed(),
 * which runs immediately after the execution was set to withSuccessfulAttempt($response). The
 * moment the cache held something, the execution held the same instance, and nothing later
 * clears it. Measured over the full suite before removal: get() reached 390 times, 0 hits.
 * The execution IS the cache, and it works for every ResponseCachePolicy rather than only
 * ::Memory. See ResponseCachePolicyEffectTest, which pins repeated response() as identical.
 *
 * ResponseCachePolicy itself still matters, elsewhere: BaseInferenceRequestDriver maps it onto
 * the HTTP layer's StreamCachePolicy, which is what makes a stream replayable.
 */
final class InferenceExecutionSession
{
    private readonly InferenceLifecycleEmitter $emitter;
    private readonly InferenceRetryLoop $retry;

    private ?InferenceStream $cachedStream = null;

    private ?\Throwable $terminalError = null;

    /**
     * True while response() is driving the stream to completion itself.
     *
     * THIS COULD NOT BE REMOVED, and the reason is worth stating rather than leaving to be
     * rediscovered. response() on a streamed request runs the retry loop, whose body calls
     * stream()->final(); finalising the stream calls back into onStreamFinalized(), so the
     * retry loop and the stream callback both arrive at the end of the same execution. Only
     * one may terminate it.
     *
     * A one-shot latch on terminate()/succeed() would stop the double dispatch, but not
     * equivalently: the callback runs BEFORE the loop applies withSuccessfulAttempt(), so
     * letting the callback win would emit the same events in the same order carrying a
     * telemetry envelope built from an execution one step behind. The flag is not
     * deduplication -- it names which of the two owns the ending, and the retry loop does,
     * because it is the one that knows whether another attempt is coming.
     */
    private bool $finalizingCachedStreamViaResponse = false;

    /**
     * @param (callable(): int)|null $monotonicNanoReader Injectable monotonic clock
     *        (nanoseconds) used only by tests to simulate long durations; production
     *        defaults to hrtime.
     */
    public function __construct(
        private InferenceExecution $execution,
        private readonly CanProcessInferenceRequest $driver,
        private readonly EventDispatcherInterface $events,
        ?callable $monotonicNanoReader = null,
    ) {
        $this->emitter = new InferenceLifecycleEmitter(
            events: $events,
            executionCorrelation: $execution->request()->telemetryCorrelation(),
            monotonicNanoReader: $monotonicNanoReader,
        );
        $this->retry = new InferenceRetryLoop($execution->request()->retryPolicy());
    }

    public function isStreamed(): bool {
        return $this->execution->request()->isStreamed();
    }

    public function executionId(): string {
        return $this->execution->id->toString();
    }

    public function stream(): InferenceStream {
        if (! $this->isStreamed()) {
            throw new InvalidArgumentException('Trying to read response stream for request with no streaming');
        }

        if ($this->cachedStream !== null) {
            return $this->cachedStream;
        }

        $this->emitter->executionStarted($this->execution);

        $currentAttempt = $this->execution->currentAttempt();
        if ($currentAttempt === null || $currentAttempt->isFinalized()) {
            $this->execution = $this->emitter->beginAttempt($this->execution);
        }

        // I7: both callables are built exactly once per session, because the cache above
        // short-circuits every later call. Do not move this into a per-attempt method.
        $this->cachedStream = new InferenceStream(
            execution: $this->execution,
            driver: $this->driver,
            eventDispatcher: $this->events,
            decorateFinalResponse: null,
            onFinalizedExecution: $this->onStreamFinalized(...),
            onStreamFailed: $this->onStreamFailed(...),
        );

        return $this->cachedStream;
    }

    public function response(): InferenceResponse {
        if ($this->terminalError !== null) {
            throw $this->terminalError;
        }

        $existingResponse = $this->execution->response();
        if ($existingResponse !== null) {
            return $existingResponse;
        }

        if ($this->cachedStream !== null) {
            return $this->responseFromExistingStream();
        }

        return $this->executeResponseLifecycle();
    }

    private function executeResponseLifecycle(): InferenceResponse {
        $this->emitter->executionStarted($this->execution);

        while (true) {
            $this->cachedStream = null;
            $this->execution = $this->emitter->beginAttempt($this->execution);

            try {
                $response = $this->makeResponse($this->execution->request());
            } catch (\Throwable $error) {
                $shouldRetry = $this->retry->shouldRetryAfterException($error, $this->emitter->attemptNumber());
                $this->execution = $this->execution->withFailedAttempt(null, $this->livePartialUsage(), $error);

                if (! $shouldRetry) {
                    $this->terminate($error, response: null, throw: true);
                }

                $this->emitter->attemptFailed($this->execution, $error, $this->livePartialUsage(), willRetry: true);
                $this->retry->awaitRetryDelay($this->emitter->attemptNumber());

                continue;
            }

            $this->execution = match (true) {
                $response->hasFinishedWithFailure() => $this->execution->withFailedAttempt(
                    response: $response,
                    usage: $response->usage(),
                ),
                default => $this->execution->withSuccessfulAttempt(response: $response),
            };

            if ($response->hasFinishedWithFailure()) {
                $this->handleFailedResponse($response);

                continue;
            }

            $this->succeed($response);

            return $response;
        }
    }

    private function responseFromExistingStream(): InferenceResponse {
        try {
            $response = $this->cachedStream?->final()
                ?? throw new \RuntimeException('Failed to generate final response from stream');
        } catch (\Throwable $error) {
            throw $this->terminalError ?? $error;
        }

        if ($this->terminalError !== null) {
            throw $this->terminalError;
        }

        return $response;
    }

    /**
     * Returns only when the response is recoverable and the caller should loop again.
     * Every other outcome terminates the execution by throwing.
     */
    private function handleFailedResponse(InferenceResponse $response): void {
        $action = $this->retry->actionForFailedResponse($response);

        if ($action === InferenceFailureAction::RecoverFromLength) {
            $error = new \RuntimeException('Inference execution hit length limit; retrying recovery');
            $this->emitter->attemptFailed($this->execution, $error, $response->usage(), willRetry: true);
            $this->execution = $this->execution->withRequest(
                $this->retry->lengthRecoveryRequest($this->execution->request(), $response),
            );

            return;
        }

        $this->terminate(
            match ($action) {
                InferenceFailureAction::ContentFilterBlocked => new \RuntimeException('Inference blocked by content filter'),
                default => new \RuntimeException('Inference execution failed: '.$response->finishReason()->value),
            },
            $response,
            throw: true,
        );
    }

    /**
     * The one place an execution ends well.
     */
    private function succeed(InferenceResponse $response): void {
        $this->emitter->attemptSucceeded($this->execution, $response);
        $this->emitter->executionCompleted($this->execution, isSuccess: true);
    }

    /**
     * The one place an execution ends badly. This sequence used to be written out five
     * times, four of them throwing and one storing the error for the next response() call
     * to rethrow -- a real difference that was invisible without reading all five. $throw
     * states it.
     *
     * @param bool $throw Callers driving the execution throw; stream callbacks, which are
     *        invoked from inside the stream's own iteration, store instead.
     */
    private function terminate(\Throwable $error, ?InferenceResponse $response, bool $throw): void {
        $this->emitter->attemptFailed(
            $this->execution,
            $error,
            $response?->usage() ?? $this->livePartialUsage(),
            willRetry: false,
        );
        $this->emitter->executionCompleted($this->execution, isSuccess: false);
        $this->terminalError = $error;

        if ($throw) {
            throw $error;
        }
    }

    private function makeResponse(InferenceRequest $request): InferenceResponse {
        return match ($this->isStreamed()) {
            false => $this->driver->makeResponseFor($request),
            true => $this->finalizeCachedStreamForResponse(),
        };
    }

    private function finalizeCachedStreamForResponse(): InferenceResponse {
        $this->finalizingCachedStreamViaResponse = true;

        try {
            return $this->stream()->final() ?? throw new \RuntimeException('Failed to generate final response from stream');
        } finally {
            $this->finalizingCachedStreamViaResponse = false;
        }
    }

    private function onStreamFinalized(InferenceExecution $execution): void {
        $this->execution = $execution;
        $response = $execution->response();

        if ($response === null || $this->finalizingCachedStreamViaResponse) {
            return;
        }

        if ($response->hasFinishedWithFailure()) {
            $this->terminate(
                new \RuntimeException('Inference execution failed: '.$response->finishReason()->value),
                $response,
                throw: false,
            );

            return;
        }

        $this->succeed($response);
    }

    private function onStreamFailed(\Throwable $error, InferenceUsage $partialUsage): void {
        if ($this->finalizingCachedStreamViaResponse) {
            return;
        }

        $this->execution = $this->execution->withFailedAttempt(null, $partialUsage, $error);
        $this->terminate($error, response: null, throw: false);
    }

    private function livePartialUsage(): InferenceUsage {
        return $this->cachedStream?->usage() ?? InferenceUsage::none();
    }
}
