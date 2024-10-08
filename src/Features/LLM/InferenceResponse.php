<?php

namespace Cognesy\Instructor\Features\LLM;

use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Inference\LLMResponseReceived;
use Cognesy\Instructor\Events\Inference\PartialLLMResponseReceived;
use Cognesy\Instructor\Features\Http\Contracts\CanAccessResponse;
use Cognesy\Instructor\Features\Http\IterableReader;
use Cognesy\Instructor\Features\LLM\Contracts\CanHandleInference;
use Cognesy\Instructor\Features\LLM\Data\LLMConfig;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
use Cognesy\Instructor\Utils\Json\Json;
use Generator;
use InvalidArgumentException;

class InferenceResponse
{
    protected EventDispatcher $events;
    protected IterableReader $reader;
    protected string $responseContent = '';

    public function __construct(
        protected CanAccessResponse  $response,
        protected CanHandleInference $driver,
        protected LLMConfig          $config,
        protected bool               $isStreamed = false,
        ?EventDispatcher             $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->reader = new IterableReader($this->driver->getData(...), $this->events);
    }

    public function isStreamed() : bool {
        return $this->isStreamed;
    }

    public function toText() : string {
        return match($this->isStreamed) {
            false => $this->toLLMResponse()->content,
            true => $this->getStreamContent($this->toPartialLLMResponses()),
        };
    }

    public function toJson() : array {
        return Json::from($this->toText())->toArray();
    }

    /**
     * @return Generator<string>
     */
    public function stream() : Generator {
        if (!$this->isStreamed) {
            throw new InvalidArgumentException('Trying to read response stream for request with no streaming');
        }
        foreach ($this->toPartialLLMResponses() as $partialLLMResponse) {
            yield $partialLLMResponse->delta;
        }
    }

    // AS API RESPONSE OBJECTS //////////////////////////////////

    public function toLLMResponse() : LLMResponse {
        $response = match($this->isStreamed) {
            false => $this->driver->toLLMResponse($this->responseData()),
            true => LLMResponse::fromPartialResponses($this->allPartialLLMResponses()),
        };
        $this->events->dispatch(new LLMResponseReceived($response));
        return $response;
    }

    /**
     * @return Generator<\Cognesy\Instructor\Features\LLM\Data\PartialLLMResponse>
     */
    public function toPartialLLMResponses() : Generator {
        foreach ($this->reader->stream($this->response->streamContents()) as $partialData) {
            if ($partialData === false) {
                continue;
            }
            $response = $this->driver->toPartialLLMResponse(Json::fromPartial($partialData)->toArray());
            if ($response === null) {
                continue;
            }
            $this->events->dispatch(new PartialLLMResponseReceived($response));
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

//    public function psrResponse() : ResponseInterface {
//        return $this->response;
//    }
//
//    public function psrStream() : StreamInterface {
//        return $this->response->getBody();
//    }

    // INTERNAL /////////////////////////////////////////////////

    protected function responseData() : array {
        if (empty($this->responseContent)) {
            $this->responseContent = $this->response->getContents();
        }
        return Json::parse($this->responseContent) ?? [];
    }

    /**
     * @return array[]
     */
    protected function allStreamResponses() : array {
        $content = [];
        foreach ($this->reader->stream($this->response->streamContents()) as $partialData) {
            $content[] = Json::parse($partialData);
        }
        return $content;
    }

    /**
     * @return \Cognesy\Instructor\Features\LLM\Data\PartialLLMResponse[]
     */
    protected function allPartialLLMResponses() : array {
        $partialResponses = [];
        foreach ($this->toPartialLLMResponses() as $partialResponse) {
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