<?php

namespace Cognesy\Polyglot\LLM;

use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Polyglot\LLM\Contracts\CanHandleInference;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\Data\LLMResponse;
use Cognesy\Polyglot\LLM\Events\LLMResponseReceived;
use Cognesy\Utils\Events\EventDispatcher;
use Cognesy\Utils\Json\Json;
use InvalidArgumentException;

/**
 * Represents an inference response handling object that processes responses
 * based on the configuration and streaming state. Provides methods to
 * retrieve the response in different formats.
 */
class InferenceResponse
{
    protected EventDispatcher $events;
    protected HttpClientResponse $response;
    protected CanHandleInference $driver;
    protected string $responseContent = '';
    protected LLMConfig $config;
    protected bool $isStreamed = false;

    public function __construct(
        HttpClientResponse    $response,
        CanHandleInference $driver,
        LLMConfig          $config,
        bool               $isStreamed = false,
        ?EventDispatcher   $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->driver = $driver;
        $this->config = $config;
        $this->isStreamed = $isStreamed;
        $this->response = $response;
    }

    /**
     * Determines whether the content is streamed.
     *
     * @return bool True if the content is being streamed, false otherwise.
     */
    public function isStreamed() : bool {
        return $this->isStreamed;
    }

    /**
     * Converts the response to its text representation.
     *
     * @return string The textual representation of the response. If streaming, retrieves the final content; otherwise, retrieves the standard content.
     */
    public function toText() : string {
        return match($this->isStreamed) {
            false => $this->makeLLMResponse()->content(),
            true => $this->stream()->final()?->content() ?? '',
        };
    }

    /**
     * Converts the current content to a JSON representation.
     *
     * @return array The JSON representation of the content as an associative array.
     */
    public function toJson() : array {
        return Json::fromString($this->toText())->toArray();
    }

    /**
     * Initiates and returns an inference stream for the response.
     *
     * @return InferenceStream The initialized inference stream.
     * @throws InvalidArgumentException If the response is not configured for streaming.
     */
    public function stream() : InferenceStream {
        if (!$this->isStreamed) {
            throw new InvalidArgumentException('Trying to read response stream for request with no streaming');
        }
        return new InferenceStream(
            response: $this->response,
            driver: $this->driver,
            config: $this->config,
            events: $this->events
        );
    }

    // AS API RESPONSE OBJECT ///////////////////////////////////

    /**
     * Generates and returns an LLMResponse based on the streaming status.
     *
     * @return LLMResponse The constructed LLMResponse object, either fully or from partial responses if streaming is enabled.
     */
    public function response() : LLMResponse {
        $response = match($this->isStreamed) {
            false => $this->makeLLMResponse(),
            true => LLMResponse::fromPartialResponses($this->stream()->all()),
        };
        return $response;
    }

    // INTERNAL /////////////////////////////////////////////////

    /**
     * Processes and generates a response from the Language Learning Model (LLM) driver.
     *
     * @return LLMResponse The generated response from the LLM driver.
     */
    private function makeLLMResponse() : LLMResponse {
        $content = $this->getResponseContent();
        $data = Json::decode($content) ?? [];
        $response = $this->driver->fromResponse($data);
        $this->events->dispatch(new LLMResponseReceived($response));
        return $response;
    }

    // PRIVATE /////////////////////////////////////////////////

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
        return $this->response->body();
    }
}