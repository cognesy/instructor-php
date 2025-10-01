<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference;

use Cognesy\Polyglot\Inference\Contracts\CanHandleInference;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Utils\Json\Json;
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
     * @return array The JSON representation of the content as an associative array.
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
        $response = $this->makeResponse($this->execution->request());
        $this->execution = match(true) {
            $response->hasFinishedWithFailure() => $this->execution->withFailedResponse($response),
            default => $this->execution->withNewResponse($response),
        };
        if ($response->hasFinishedWithFailure()) {
            throw new \RuntimeException('Inference execution failed: ' . $response->finishReason()->value);
        }
        return $response;
    }

    // INTERNAL ////////////////////////////////////////////////////////////////

    private function makeResponse(InferenceRequest $request) : InferenceResponse {
        return match($this->isStreamed()) {
            false => $this->driver->makeResponseFor($request),
            true => $this->stream()->final(),
        };
    }
}