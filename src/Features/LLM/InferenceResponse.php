<?php

namespace Cognesy\Instructor\Features\LLM;

use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Inference\LLMResponseReceived;
use Cognesy\Instructor\Features\Http\Contracts\CanAccessResponse;
use Cognesy\Instructor\Features\LLM\Contracts\CanHandleInference;
use Cognesy\Instructor\Features\LLM\Data\LLMConfig;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
use Cognesy\Instructor\Utils\Json\Json;
use InvalidArgumentException;

class InferenceResponse
{
    protected EventDispatcher $events;
    protected CanAccessResponse $response;
    protected CanHandleInference $driver;
    protected string $responseContent = '';
    protected LLMConfig $config;
    protected bool $isStreamed = false;

    public function __construct(
        CanAccessResponse $response,
        CanHandleInference $driver,
        LLMConfig $config,
        bool $isStreamed = false,
        ?EventDispatcher $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->driver = $driver;
        $this->config = $config;
        $this->isStreamed = $isStreamed;
        $this->response = $response;
    }

    public function isStreamed() : bool {
        return $this->isStreamed;
    }

    public function toText() : string {
        return match($this->isStreamed) {
            false => $this->makeLLMResponse()->content(),
            true => $this->stream()->final()?->content() ?? '',
        };
    }

    public function toJson() : array {
        return Json::from($this->toText())->toArray();
    }

    /**
     * @return InferenceStream
     */
    public function stream() : InferenceStream {
        if (!$this->isStreamed) {
            throw new InvalidArgumentException('Trying to read response stream for request with no streaming');
        }
        return new InferenceStream($this->response, $this->driver, $this->config, $this->events);
    }

    // AS API RESPONSE OBJECT ///////////////////////////////////

    public function response() : LLMResponse {
        $response = match($this->isStreamed) {
            false => $this->makeLLMResponse(),
            true => LLMResponse::fromPartialResponses($this->stream()->all()),
        };
        return $response;
    }

    // INTERNAL /////////////////////////////////////////////////

    private function makeLLMResponse() : LLMResponse {
        $content = $this->getResponseContent();
        $data = Json::decode($content) ?? [];
        $response = $this->driver->toLLMResponse($data);
        $this->events->dispatch(new LLMResponseReceived($response));
        return $response;
    }

    // PRIVATE /////////////////////////////////////////////////

    private function getResponseContent() : string {
        if (empty($this->responseContent)) {
            $this->responseContent = $this->readFromResponse();
        }
        return $this->responseContent;
    }

    private function readFromResponse() : string {
        return $this->response->getContents();
    }
}