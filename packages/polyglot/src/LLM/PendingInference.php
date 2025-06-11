<?php

namespace Cognesy\Polyglot\LLM;

use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Polyglot\LLM\Contracts\CanHandleInference;
use Cognesy\Polyglot\LLM\Data\InferenceResponse;
use Cognesy\Polyglot\LLM\Events\InferenceFailed;
use Cognesy\Polyglot\LLM\Events\InferenceRequested;
use Cognesy\Polyglot\LLM\Events\LLMResponseCreated;
use Cognesy\Utils\Json\Json;
use Exception;
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
    protected InferenceRequest $request;
    protected EventDispatcherInterface $events;
    protected HttpClientResponse $httpResponse;
    protected CanHandleInference $driver;
    protected string $responseContent = '';

    public function __construct(
        InferenceRequest         $request,
        CanHandleInference       $driver,
        EventDispatcherInterface $eventDispatcher,
    ) {
        $this->request = $request;
        $this->events = $eventDispatcher;
        $this->driver = $driver;
    }

    /**
     * Determines whether the content is streamed.
     *
     * @return bool True if the content is being streamed, false otherwise.
     */
    public function isStreamed() : bool {
        return $this->request->isStreamed();
    }

    /**
     * Converts the response to its text representation.
     *
     * @return string The textual representation of the response. If streaming, retrieves the final content; otherwise, retrieves the standard content.
     */
    public function get() : string {
        return match($this->isStreamed()) {
            false => $this->tryMakeLLMResponse()->content(),
            true => $this->stream()->final()?->content() ?? '',
        };
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
            httpResponse: $this->httpResponse(),
            driver: $this->driver,
            eventDispatcher: $this->events->dispatcher(),
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
     * Generates and returns an LLMResponse based on the streaming status.
     *
     * @return InferenceResponse The constructed LLMResponse object, either fully or from partial responses if streaming is enabled.
     */
    public function response() : InferenceResponse {
        $response = match($this->isStreamed()) {
            false => $this->tryMakeLLMResponse(),
            true => InferenceResponse::fromPartialResponses($this->stream()->all()),
        };
        return $response;
    }

    // INTERNAL /////////////////////////////////////////////////

    private function tryMakeLLMResponse() : InferenceResponse {
        try {
            return $this->makeLLMResponse();
        } catch (Exception $e) {
            $this->events->dispatch(new InferenceFailed([
                'exception' => $e->getMessage(),
                'statusCode' => $this->httpResponse()->statusCode(),
                'headers' => $this->httpResponse()->headers(),
                'body' => $this->httpResponse()->body(),
            ]));
            throw $e;
        }
    }

    private function httpResponse() : HttpClientResponse {
        if (!isset($this->httpResponse)) {
            $this->events->dispatch(new InferenceRequested(['request' => $this->request->toArray()]));
            $this->httpResponse = $this->driver->handle($this->request);
        }
        return $this->httpResponse;
    }

    /**
     * Processes and generates a response from the Language Learning Model (LLM) driver.
     *
     * @return InferenceResponse The generated response from the LLM driver.
     */
    private function makeLLMResponse() : InferenceResponse {
        $content = $this->getResponseContent();
        $data = Json::decode($content) ?? [];
        $response = $this->driver->fromResponse($data);
        $this->events->dispatch(new LLMResponseCreated($response));
        return $response;
    }

    /**
     * Retrieves the content of the response. If the content has not been
     * set, it reads and initializes the content from the response.
     *
     * @return string The content of the response.
     */
    private function getResponseContent() : string {
        if (empty($this->responseContent)) {
            $this->responseContent = $this->readFromResponse();
        }
        return $this->responseContent;
    }

    /**
     * Reads and retrieves the contents from the response.
     *
     * @return string The contents of the response.
     */
    private function readFromResponse() : string {
        return $this->httpResponse()->body();
    }
}