<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference;

use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Creation\InferenceRequestBuilder;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\Pricing;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptFailed;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptStarted;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptSucceeded;
use Cognesy\Polyglot\Inference\Events\InferenceCompleted;
use Cognesy\Polyglot\Inference\Events\InferenceStarted;
use Cognesy\Polyglot\Inference\Events\InferenceUsageReported;
use Cognesy\Polyglot\Inference\Streaming\InferenceStream;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Messages\Messages;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Represents an inference response handling object that processes responses
 * based on the configuration and streaming state. Provides methods to
 * retrieve the response in different formats. PendingInference does not
 * execute any request to the underlying LLM API until the data is accessed
 * via its methods (`get()`, `response()`).
 */
class PendingInference
{
    protected readonly CanProcessInferenceRequest $driver;
    protected readonly EventDispatcherInterface $events;

    protected InferenceExecution $execution;
    private ?Pricing $pricing;

    private ?DateTimeImmutable $startedAt = null;
    private ?DateTimeImmutable $attemptStartedAt = null;
    private int $attemptNumber = 0;
    private ?InferenceResponse $cachedResponse = null;
    private ?InferenceStream $cachedStream = null;
    private ?\Throwable $terminalError = null;

    public function __construct(
        InferenceExecution         $execution,
        CanProcessInferenceRequest $driver,
        EventDispatcherInterface   $eventDispatcher,
        ?Pricing                   $pricing = null,
    ) {
        $this->execution = $execution;
        $this->events = $eventDispatcher;
        $this->driver = $driver;
        $this->pricing = $pricing;
    }

    /**
     * Determines whether the content is streamed.
     *
     * @return bool True if the content is being streamed, false otherwise.
     */
    public function isStreamed() : bool {
        return $this->execution->request()->isStreamed();
    }

    /**
     * Converts the response to its text representation.
     *
     * @return string The textual representation of the response. If streaming, retrieves the final content; otherwise, retrieves the standard content.
     */
    public function get() : string {
        return $this->response()->content();
    }

    /**
     * Initiates and returns an inference stream for the response.
     *
     * @return InferenceStream The initialized inference stream.
     * Streaming via this method does not apply retry logic; errors propagate to the caller.
     * @throws InvalidArgumentException If the response is not configured for streaming.
     */
    public function stream() : InferenceStream {
        if (!$this->isStreamed()) {
            throw new InvalidArgumentException('Trying to read response stream for request with no streaming');
        }

        if ($this->cachedStream !== null) {
            return $this->cachedStream;
        }

        $this->ensureLifecycleStartedForCurrentAttempt();

        $stream = new InferenceStream(
            execution: $this->execution,
            driver: $this->driver,
            eventDispatcher: $this->events,
            decorateFinalResponse: $this->attachPricing(...),
        );
        $this->cachedStream = $stream;
        return $stream;
    }

    // AS API RESPONSE OBJECT ///////////////////////////////////

    /**
     * Converts the response content to a JSON representation.
     *
     * @return string The JSON representation of the content as a JSON string.
     */
    public function asJson() : string {
        return $this->response()
            ->findJsonData($this->execution->request()->outputMode())
            ->toString();
    }

    /**
     * Converts the response content to a JSON representation.
     *
     * @return array The JSON representation of the content as an associative array.
     */
    public function asJsonData() : array {
        return $this->response()
            ->findJsonData($this->execution->request()->outputMode())
            ->toArray();
    }

    /**
     * Generates and returns an InferenceResponse based on the streaming status.
     *
     * @return InferenceResponse The constructed InferenceResponse object, either fully or from partial responses if streaming is enabled.
     */
    public function response() : InferenceResponse {
        if ($this->terminalError !== null) {
            throw $this->terminalError;
        }
        $existingResponse = $this->execution->response();
        if ($existingResponse !== null) {
            $response = $this->attachPricing($existingResponse);
            $this->execution = match (true) {
                $response === $existingResponse => $this->execution,
                default => $this->execution->withUpdatedResponse($response),
            };
            return $response;
        }
        if ($this->shouldCache() && $this->cachedResponse !== null) {
            return $this->cachedResponse;
        }

        // If a stream was already created, delegate to it — do not re-execute
        if ($this->cachedStream !== null) {
            $response = $this->cachedStream->final()
                ?? throw new \RuntimeException('Failed to generate final response from stream');
            $response = $this->attachPricing($response);

            if ($response->hasFinishedWithFailure()) {
                $this->execution = $this->execution->withFailedAttempt(response: $response);
                $finishReason = $response->finishReason();
                $error = new \RuntimeException('Inference execution failed: ' . $finishReason->value);
                $this->handleAttemptFailure($error, $response, false);
                $this->dispatchInferenceCompleted(isSuccess: false);
                $this->terminalError = $error;
                throw $error;
            }

            $this->execution = $this->execution->withSuccessfulAttempt(response: $response);
            $this->handleAttemptSuccess($response);
            $this->dispatchInferenceCompleted(isSuccess: true);

            if ($this->shouldCache()) {
                $this->cachedResponse = $response;
            }
            return $response;
        }

        $policy = $this->execution->request()->retryPolicy() ?? new InferenceRetryPolicy();
        $maxAttempts = max(1, $policy->maxAttempts);

        $lengthRetries = 0;
        $this->dispatchInferenceStarted();

        while (true) {
            $this->cachedStream = null;
            $this->dispatchAttemptStarted();

            try {
                $response = $this->makeResponse($this->execution->request());
            } catch (\Throwable $e) {
                $shouldRetry = $this->attemptNumber < $maxAttempts
                    && $policy->shouldRetryException($e);
                $this->handleAttemptFailure($e, null, $shouldRetry);
                if (!$shouldRetry) {
                    $this->dispatchInferenceCompleted(isSuccess: false);
                    $this->terminalError = $e;
                    throw $e;
                }

                $delayMs = $policy->delayMsForAttempt($this->attemptNumber);
                if ($delayMs > 0) {
                    usleep($delayMs * 1000);
                }
                continue;
            }

            $this->execution = match(true) {
                $response->hasFinishedWithFailure() => $this->execution->withFailedAttempt(response: $response),
                default => $this->execution->withSuccessfulAttempt(response: $response),
            };

            if ($response->hasFinishedWithFailure()) {
                $finishReason = $response->finishReason();

                if ($finishReason === InferenceFinishReason::Length && $policy->shouldRecoverFromLength($lengthRetries)) {
                    $lengthRetries++;
                    $error = new \RuntimeException('Inference execution hit length limit; retrying recovery');
                    $this->handleAttemptFailure($error, $response, true);
                    $this->execution = $this->execution->withRequest(
                        $this->buildLengthRecoveryRequest($this->execution->request(), $response, $policy)
                    );
                    continue;
                }

                if ($finishReason === InferenceFinishReason::ContentFilter) {
                    $error = new \RuntimeException('Inference blocked by content filter');
                    $this->handleAttemptFailure($error, $response, false);
                    $this->dispatchInferenceCompleted(isSuccess: false);
                    $this->terminalError = $error;
                    throw $error;
                }

                $error = new \RuntimeException('Inference execution failed: ' . $finishReason->value);
                $this->handleAttemptFailure($error, $response, false);
                $this->dispatchInferenceCompleted(isSuccess: false);
                $this->terminalError = $error;
                throw $error;
            }

            $this->handleAttemptSuccess($response);
            $this->dispatchInferenceCompleted(isSuccess: true);

            if ($this->shouldCache()) {
                $this->cachedResponse = $response;
            }

            return $response;
        }
    }

    // INTERNAL ////////////////////////////////////////////////////////////////

    private function cachePolicy(): ResponseCachePolicy {
        return $this->execution->request()->responseCachePolicy();
    }

    private function shouldCache(): bool {
        return $this->cachePolicy()->shouldCache();
    }

    private function makeResponse(InferenceRequest $request) : InferenceResponse {
        return match($this->isStreamed()) {
            false => $this->attachPricing($this->driver->makeResponseFor($request)),
            true => $this->attachPricing(
                $this->stream()->final() ?? throw new \RuntimeException('Failed to generate final response from stream')
            ),
        };
    }

    private function ensureLifecycleStartedForCurrentAttempt(): void {
        $this->dispatchInferenceStarted();

        $currentAttempt = $this->execution->currentAttempt();
        if ($currentAttempt === null || $currentAttempt->isFinalized()) {
            $this->dispatchAttemptStarted();
        }
    }

    private function attachPricing(InferenceResponse $response): InferenceResponse {
        if ($this->pricing === null || !$this->pricing->hasAnyPricing()) {
            return $response;
        }
        $usagePricing = $response->usage()->pricing();
        if ($usagePricing !== null && $usagePricing->hasAnyPricing()) {
            return $response;
        }
        return $response->withPricing($this->pricing);
    }

    // EVENT DISPATCHING ///////////////////////////////////////////////////////

    private function dispatchInferenceStarted(): void {
        if ($this->startedAt !== null) {
            return; // Already started
        }
        $this->startedAt = new DateTimeImmutable();
        $this->events->dispatch(new InferenceStarted(
            executionId: $this->execution->id->toString(),
            request: $this->execution->request(),
            isStreamed: $this->isStreamed(),
        ));
    }

    private function dispatchAttemptStarted(): void {
        $this->attemptNumber++;
        $this->attemptStartedAt = new DateTimeImmutable();
        $this->execution = $this->execution->startAttempt();

        $attemptId = $this->currentAttemptId();

        $this->events->dispatch(new InferenceAttemptStarted(
            executionId: $this->execution->id->toString(),
            attemptId: $attemptId,
            attemptNumber: $this->attemptNumber,
            model: $this->execution->request()->model(),
        ));
    }

    private function handleAttemptSuccess(InferenceResponse $response): void {
        $attemptId = $this->currentAttemptId();

        $this->events->dispatch(new InferenceAttemptSucceeded(
            executionId: $this->execution->id->toString(),
            attemptId: $attemptId,
            attemptNumber: $this->attemptNumber,
            finishReason: $response->finishReason(),
            usage: $response->usage(),
            startedAt: $this->attemptStartedAt,
        ));

        $this->events->dispatch(new InferenceUsageReported(
            executionId: $this->execution->id->toString(),
            usage: $response->usage(),
            model: $this->execution->request()->model(),
            isFinal: true,
        ));
    }

    private function handleAttemptFailure(
        \Throwable $error,
        ?InferenceResponse $response = null,
        bool $willRetry = false
    ): void {
        $attemptId = $this->currentAttemptId();
        $partialUsage = $response?->usage()
            ?? $this->cachedStream?->execution()->partialResponse()?->usage()
            ?? $this->execution->partialResponse()?->usage();
        $statusCode = $error instanceof HttpRequestException ? $error->getStatusCode() : null;

        $this->events->dispatch(InferenceAttemptFailed::fromThrowable(
            executionId: $this->execution->id->toString(),
            attemptId: $attemptId,
            attemptNumber: $this->attemptNumber,
            error: $error,
            willRetry: $willRetry,
            httpStatusCode: $statusCode,
            partialUsage: $partialUsage,
            startedAt: $this->attemptStartedAt,
        ));
    }

    private function buildLengthRecoveryRequest(
        InferenceRequest $request,
        InferenceResponse $response,
        InferenceRetryPolicy $policy
    ): InferenceRequest {
        $builder = (new InferenceRequestBuilder())->withRequest($request);

        if ($policy->lengthRecovery === 'increase_max_tokens') {
            $current = $request->options()['max_tokens'] ?? null;
            $next = $current !== null
                ? $current + max(1, $policy->maxTokensIncrement)
                : max(1, $policy->maxTokensIncrement);
            return $builder->withMaxTokens($next)->create();
        }

        $messages = Messages::fromAnyArray($request->messages())
            ->asAssistant($response->content())
            ->asUser($policy->lengthContinuePrompt);

        return $builder->withMessages($messages)->create();
    }

    private function dispatchInferenceCompleted(bool $isSuccess): void {
        $response = $this->execution->response();
        $usage = $response?->usage() ?? $this->execution->usage();
        $finishReason = $response?->finishReason() ?? InferenceFinishReason::Error;

        $this->events->dispatch(new InferenceCompleted(
            executionId: $this->execution->id->toString(),
            isSuccess: $isSuccess,
            finishReason: $finishReason,
            usage: $usage,
            attemptCount: $this->attemptNumber,
            startedAt: $this->startedAt ?? new DateTimeImmutable(),
            response: $response,
        ));
    }

    private function currentAttemptId(): string {
        $currentAttempt = $this->execution->currentAttempt();
        if ($currentAttempt === null) {
            throw new \LogicException('Attempt not started before event dispatch.');
        }
        return $currentAttempt->id->toString();
    }
}
