<?php

namespace Cognesy\Instructor\Extras\LLM;

use Cognesy\Instructor\Events\ApiClient\ApiResponseReceived;
use Cognesy\Instructor\Events\ApiClient\PartialApiResponseReceived;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Inference\InferenceResponseGenerated;
use Cognesy\Instructor\Events\Inference\PartialInferenceResponseGenerated;
use Cognesy\Instructor\Extras\Http\StreamReader;
use Cognesy\Instructor\Extras\LLM\Contracts\CanHandleInference;
use Cognesy\Instructor\Extras\LLM\Data\ApiResponse;
use Cognesy\Instructor\Extras\LLM\Data\LLMConfig;
use Cognesy\Instructor\Utils\Json\Json;
use Generator;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class InferenceResponse
{
    protected EventDispatcher $events;
    protected StreamReader $streamReader;

    public function __construct(
        protected ResponseInterface $response,
        protected CanHandleInference $driver,
        protected LLMConfig $config,
        protected bool $isStreamed = false,
        ?EventDispatcher $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->streamReader = new StreamReader($this->driver->getData(...), $this->events);
    }

    public function isStreamed() : bool {
        return $this->isStreamed;
    }

    public function toText() : string {
        return match($this->isStreamed) {
            false => $this->toApiResponse()->content,
            true => $this->getStreamContent($this->toPartialApiResponses()),
        };
    }

    /**
     * @return Generator<string>
     */
    public function stream() : Generator {
        if (!$this->isStreamed) {
            throw new InvalidArgumentException('Trying to read response stream for request with no streaming');
        }
        foreach ($this->toPartialApiResponses() as $partialApiResponse) {
            yield $partialApiResponse->delta;
        }
    }

    // AS API RESPONSE OBJECTS //////////////////////////////////

    public function toApiResponse() : ApiResponse {
        $response = match($this->isStreamed) {
            false => $this->driver->toApiResponse($this->responseData()),
            true => ApiResponse::fromPartialResponses($this->allPartialApiResponses()),
        };
        $this->events->dispatch(new ApiResponseReceived($response));
        return $response;
    }

    /**
     * @return Generator<\Cognesy\Instructor\Extras\LLM\Data\PartialApiResponse>
     */
    public function toPartialApiResponses() : Generator {
        foreach ($this->streamReader->stream($this->psrStream()) as $partialData) {
            $response = $this->driver->toPartialApiResponse(Json::parse($partialData, default: []));
            $this->events->dispatch(new PartialApiResponseReceived($response));
            yield $response;
        }
    }

    // LOW LEVEL ACCESS /////////////////////////////////////////

    /**
     * @return array[]
     */
    public function asArray() : array {
        return match($this->isStreamed) {
            false => $this->responseData(),
            true => $this->allStreamResponses(),
        };
    }

    public function psrResponse() : ResponseInterface {
        return $this->response;
    }

    public function psrStream() : StreamInterface {
        return $this->response->getBody();
    }

    // INTERNAL /////////////////////////////////////////////////

    protected function responseData() : array {
        return Json::parse($this->response->getBody()->getContents()) ?? [];
    }

    /**
     * @return array[]
     */
    protected function allStreamResponses() : array {
        $content = [];
        foreach ($this->streamReader->stream($this->psrStream()) as $partialData) {
            $content[] = Json::parse($partialData);
        }
        return $content;
    }

    /**
     * @return \Cognesy\Instructor\Extras\LLM\Data\PartialApiResponse[]
     */
    protected function allPartialApiResponses() : array {
        $partialResponses = [];
        foreach ($this->toPartialApiResponses() as $partialResponse) {
            $partialResponses[] = $partialResponse;
        }
        return $partialResponses;
    }

    protected function getStreamContent(Generator $partialResponses) : string {
        $content = '';
        foreach ($partialResponses as $partialResponse) {
            $content .= $partialResponse->delta;
        }
        return $content;
    }
}