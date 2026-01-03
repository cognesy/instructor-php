<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference;

use Cognesy\Polyglot\Inference\Contracts\CanHandleInference;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptFailed;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptStarted;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptSucceeded;
use Cognesy\Polyglot\Inference\Events\InferenceCompleted;
use Cognesy\Polyglot\Inference\Events\InferenceStarted;
use Cognesy\Polyglot\Inference\Events\InferenceUsageReported;
use Cognesy\Polyglot\Inference\Streaming\InferenceStream;
use Cognesy\Utils\Json\Json;
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
    protected readonly CanHandleInference $driver;
    protected readonly EventDispatcherInterface $events;

    protected InferenceExecution $execution;

    private ?DateTimeImmutable $startedAt = null;
    private ?DateTimeImmutable $attemptStartedAt = null;
    private int $attemptNumber = 0;
    private ?InferenceResponse $cachedResponse = null;
    private ?InferenceStream $cachedStream = null;

    public function __construct(
        InferenceExecution $execution,
        CanHandleInference $driver,
        EventDispatcherInterface $eventDispatcher,
    ) {
        $this->execution = $execution;
        $this->events = $eventDispatcher;
        $this->driver = $driver;
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
     * @throws InvalidArgumentException If the response is not configured for streaming.
     */
    public function stream() : InferenceStream {
        if (!$this->isStreamed()) {
            throw new InvalidArgumentException('Trying to read response stream for request with no streaming');
        }

        if ($this->cachedStream !== null) {
            return $this->cachedStream;
        }

        $stream = new InferenceStream(
            execution: $this->execution,
            driver: $this->driver,
            eventDispatcher: $this->events,
            cachePolicy: $this->cachePolicy(),
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
        return Json::fromString($this->get())->toString();
    }

    /**
     * Converts the response content to a JSON representation.
     *
     * @return array The JSON representation of the content as an associative array.
     */
    public function asJsonData() : array {
        return Json::fromString($this->get())->toArray();
    }

    /**
     * Generates and returns an InferenceResponse based on the streaming status.
     *
     * @return InferenceResponse The constructed InferenceResponse object, either fully or from partial responses if streaming is enabled.
     */
    public function response() : InferenceResponse {
        $existingResponse = $this->execution->response();
        if ($existingResponse !== null) {
            return $existingResponse;
        }
        if ($this->shouldCache() && $this->cachedResponse !== null) {
            return $this->cachedResponse;
        }

        $this->dispatchInferenceStarted();
        $this->dispatchAttemptStarted();

        try {
            $response = $this->makeResponse($this->execution->request());
        } catch (\Throwable $e) {
            $this->handleAttemptFailure($e);
            $this->dispatchInferenceCompleted(isSuccess: false);
            throw $e;
        }

        $this->execution = match(true) {
            $response->hasFinishedWithFailure() => $this->execution->withFailedResponse($response),
            default => $this->execution->withNewResponse($response),
        };

        if ($response->hasFinishedWithFailure()) {
            $error = new \RuntimeException('Inference execution failed: ' . $response->finishReason()->value);
            $this->handleAttemptFailure($error, $response);
            $this->dispatchInferenceCompleted(isSuccess: false);
            throw $error;
        }

        $this->handleAttemptSuccess($response);
        $this->dispatchInferenceCompleted(isSuccess: true);

        if ($this->shouldCache()) {
            $this->cachedResponse = $response;
        }

        return $response;
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
            false => $this->driver->makeResponseFor($request),
            true => $this->stream()->final() ?? throw new \RuntimeException('Failed to generate final response from stream'),
        };
    }

    // EVENT DISPATCHING ///////////////////////////////////////////////////////

    private function dispatchInferenceStarted(): void {
        if ($this->startedAt !== null) {
            return; // Already started
        }
        $this->startedAt = new DateTimeImmutable();
        $this->events->dispatch(new InferenceStarted(
            executionId: $this->execution->id,
            request: $this->execution->request(),
            isStreamed: $this->isStreamed(),
        ));
    }

    private function dispatchAttemptStarted(): void {
        $this->attemptNumber++;
        $this->attemptStartedAt = new DateTimeImmutable();

        $attemptId = $this->getCurrentAttemptId();

        $this->events->dispatch(new InferenceAttemptStarted(
            executionId: $this->execution->id,
            attemptId: $attemptId,
            attemptNumber: $this->attemptNumber,
            model: $this->execution->request()->model(),
        ));
    }

    private function handleAttemptSuccess(InferenceResponse $response): void {
        $attemptId = $this->getCurrentAttemptId();

        $this->events->dispatch(new InferenceAttemptSucceeded(
            executionId: $this->execution->id,
            attemptId: $attemptId,
            attemptNumber: $this->attemptNumber,
            finishReason: $response->finishReason(),
            usage: $response->usage(),
            startedAt: $this->attemptStartedAt,
        ));

        $this->events->dispatch(new InferenceUsageReported(
            executionId: $this->execution->id,
            usage: $response->usage(),
            model: $this->execution->request()->model(),
            isFinal: true,
        ));
    }

    private function handleAttemptFailure(\Throwable $error, ?InferenceResponse $response = null): void {
        $attemptId = $this->getCurrentAttemptId();
        $partialUsage = $response?->usage() ?? $this->execution->partialResponse()?->usage();

        $this->events->dispatch(InferenceAttemptFailed::fromThrowable(
            executionId: $this->execution->id,
            attemptId: $attemptId,
            attemptNumber: $this->attemptNumber,
            error: $error,
            willRetry: false,
            httpStatusCode: null,
            partialUsage: $partialUsage,
            startedAt: $this->attemptStartedAt,
        ));
    }

    private function dispatchInferenceCompleted(bool $isSuccess): void {
        $response = $this->execution->response();
        $usage = $response?->usage() ?? $this->execution->usage();
        $finishReason = $response?->finishReason() ?? InferenceFinishReason::Error;

        $this->events->dispatch(new InferenceCompleted(
            executionId: $this->execution->id,
            isSuccess: $isSuccess,
            finishReason: $finishReason,
            usage: $usage,
            attemptCount: $this->attemptNumber,
            startedAt: $this->startedAt ?? new DateTimeImmutable(),
            response: $response,
        ));
    }

    private function getCurrentAttemptId(): string {
        return $this->execution->attempts()->count() > 0
            ? $this->execution->attempts()->last()?->id ?? $this->execution->id
            : $this->execution->id;
    }
}
