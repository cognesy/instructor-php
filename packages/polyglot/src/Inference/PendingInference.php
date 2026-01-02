<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference;

use Cognesy\Polyglot\Inference\Contracts\CanHandleInference;
use Cognesy\Polyglot\Inference\Data\InferenceAttemptStats;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptFailed;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptStarted;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptStatsReported;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptSucceeded;
use Cognesy\Polyglot\Inference\Events\InferenceCompleted;
use Cognesy\Polyglot\Inference\Events\InferenceExecutionStatsReported;
use Cognesy\Polyglot\Inference\Events\InferenceStarted;
use Cognesy\Polyglot\Inference\Events\InferenceUsageReported;
use Cognesy\Polyglot\Inference\Stats\InferenceStatsCalculator;
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
    /** @var InferenceAttemptStats[] */
    private array $attemptStats = [];
    private InferenceStatsCalculator $statsCalculator;

    public function __construct(
        InferenceExecution $execution,
        CanHandleInference $driver,
        EventDispatcherInterface $eventDispatcher,
    ) {
        $this->execution = $execution;
        $this->events = $eventDispatcher;
        $this->driver = $driver;
        $this->statsCalculator = new InferenceStatsCalculator();
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

        return new InferenceStream(
            execution: $this->execution,
            driver: $this->driver,
            eventDispatcher: $this->events,
        );
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
        $this->dispatchInferenceStarted();
        $this->dispatchAttemptStarted();

        try {
            $response = $this->makeResponse($this->execution->request());
        } catch (\Throwable $e) {
            $this->handleAttemptFailure($e);
            $this->dispatchExecutionStats(isSuccess: false);
            throw $e;
        }

        $this->execution = match(true) {
            $response->hasFinishedWithFailure() => $this->execution->withFailedResponse($response),
            default => $this->execution->withNewResponse($response),
        };

        if ($response->hasFinishedWithFailure()) {
            $error = new \RuntimeException('Inference execution failed: ' . $response->finishReason()->value);
            $this->handleAttemptFailure($error, $response);
            $this->dispatchExecutionStats(isSuccess: false);
            throw $error;
        }

        $this->handleAttemptSuccess($response);
        $this->dispatchExecutionStats(isSuccess: true);

        return $response;
    }

    // INTERNAL ////////////////////////////////////////////////////////////////

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

        // Dispatch basic attempt succeeded event
        $this->events->dispatch(new InferenceAttemptSucceeded(
            executionId: $this->execution->id,
            attemptId: $attemptId,
            attemptNumber: $this->attemptNumber,
            finishReason: $response->finishReason(),
            usage: $response->usage(),
            startedAt: $this->attemptStartedAt,
        ));

        // Calculate and dispatch attempt stats
        $attemptStats = $this->statsCalculator->calculateAttemptStatsFromResponse(
            response: $response,
            executionId: $this->execution->id,
            attemptId: $attemptId,
            attemptNumber: $this->attemptNumber,
            startedAt: $this->attemptStartedAt ?? new DateTimeImmutable(),
            timeToFirstChunkMs: null, // Non-streaming doesn't have TTFC
            model: $this->execution->request()->model(),
            isStreamed: $this->isStreamed(),
        );
        $this->attemptStats[] = $attemptStats;
        $this->events->dispatch(new InferenceAttemptStatsReported($attemptStats));

        // Dispatch usage event
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

        // Dispatch basic attempt failed event
        $this->events->dispatch(InferenceAttemptFailed::fromThrowable(
            executionId: $this->execution->id,
            attemptId: $attemptId,
            attemptNumber: $this->attemptNumber,
            error: $error,
            willRetry: false, // Retry logic is handled at higher level (Instructor)
            httpStatusCode: null,
            partialUsage: $partialUsage,
            startedAt: $this->attemptStartedAt,
        ));

        // Calculate and dispatch attempt stats
        $attemptStats = $this->statsCalculator->calculateFailedAttemptStats(
            executionId: $this->execution->id,
            attemptId: $attemptId,
            attemptNumber: $this->attemptNumber,
            startedAt: $this->attemptStartedAt ?? new DateTimeImmutable(),
            error: $error,
            partialUsage: $partialUsage,
            model: $this->execution->request()->model(),
            isStreamed: $this->isStreamed(),
        );
        $this->attemptStats[] = $attemptStats;
        $this->events->dispatch(new InferenceAttemptStatsReported($attemptStats));
    }

    private function dispatchExecutionStats(bool $isSuccess): void {
        $response = $this->execution->response();
        $usage = $response?->usage() ?? $this->execution->usage();
        $finishReason = $response?->finishReason() ?? InferenceFinishReason::Error;

        // Dispatch completion event
        $this->events->dispatch(new InferenceCompleted(
            executionId: $this->execution->id,
            isSuccess: $isSuccess,
            finishReason: $finishReason,
            usage: $usage,
            attemptCount: $this->attemptNumber,
            startedAt: $this->startedAt ?? new DateTimeImmutable(),
            response: $response,
        ));

        // Calculate and dispatch execution stats
        $executionStats = $this->statsCalculator->calculateExecutionStats(
            execution: $this->execution,
            startedAt: $this->startedAt ?? new DateTimeImmutable(),
            timeToFirstChunkMs: null, // Will be set by InferenceStream for streaming
            model: $this->execution->request()->model(),
            isStreamed: $this->isStreamed(),
            attemptStats: $this->attemptStats,
        );
        $this->events->dispatch(new InferenceExecutionStatsReported($executionStats));
    }

    private function getCurrentAttemptId(): string {
        return $this->execution->attempts()->count() > 0
            ? $this->execution->attempts()->last()?->id ?? $this->execution->id
            : $this->execution->id;
    }
}