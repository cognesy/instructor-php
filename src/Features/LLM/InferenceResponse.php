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
use Cognesy\Instructor\Features\LLM\Data\PartialLLMResponse;
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
            false => $this->asLLMResponse()->content,
            true => $this->finalResponse($this->asPartialLLMResponses())->content(),
        };
    }

    public function toJson() : array {
        return Json::from($this->toText())->toArray();
    }

    /**
     * @return Generator<PartialLLMResponse>
     */
    public function stream() : Generator {
        if (!$this->isStreamed) {
            throw new InvalidArgumentException('Trying to read response stream for request with no streaming');
        }
        foreach ($this->asPartialLLMResponses() as $partialLLMResponse) {
            yield $partialLLMResponse;
        }
    }

    // AS API RESPONSE OBJECTS //////////////////////////////////

    public function asLLMResponse() : LLMResponse {
        $response = match($this->isStreamed) {
            false => $this->driver->toLLMResponse($this->responseData()),
            true => LLMResponse::fromPartialResponses($this->allPartialLLMResponses()),
        };
        $this->events->dispatch(new LLMResponseReceived($response));
        return $response;
    }

    /**
     * @return Generator<PartialLLMResponse>
     */
    public function asPartialLLMResponses() : Generator {
        $content = '';
        $finishReason = '';
        foreach ($this->reader->toStreamEvents($this->response->streamContents()) as $streamEvent) {
            if ($streamEvent === false) {
                continue;
            }
            $data = Json::decode($streamEvent, []);
            $partialResponse = $this->driver->toPartialLLMResponse($data);
            if ($partialResponse === null) {
                continue;
            }
            if ($partialResponse->finishReason !== '') {
                $finishReason = $partialResponse->finishReason;
            }
            $content .= $partialResponse->contentDelta;
            // add accumulated content and last finish reason
            $enrichedResponse = $partialResponse
                ->withContent($content)
                ->withFinishReason($finishReason);
            $this->events->dispatch(new PartialLLMResponseReceived($enrichedResponse));
            yield $enrichedResponse;
        }
    }

    /**
     * @return array[]
     */
    public function asArray() : array {
        return match($this->isStreamed) {
            false => $this->responseData(),
            true => $this->allStreamResponses(),
        };
    }

    // INTERNAL /////////////////////////////////////////////////

    protected function responseData() : array {
        if (empty($this->responseContent)) {
            $this->responseContent = $this->response->getContents();
        }
        return Json::decode($this->responseContent) ?? [];
    }

    /**
     * @return array[]
     */
    protected function allStreamResponses() : array {
        $content = [];
        foreach ($this->reader->toStreamEvents($this->response->streamContents()) as $partialData) {
            $content[] = Json::decode($partialData);
        }
        return $content;
    }

    /**
     * @return PartialLLMResponse[]
     */
    protected function allPartialLLMResponses() : array {
        $partialResponses = [];
        foreach ($this->asPartialLLMResponses() as $partialResponse) {
            $partialResponses[] = $partialResponse;
        }
        return $partialResponses;
    }

    protected function finalResponse(Generator $partialResponses) : PartialLLMResponse {
        $lastPartial = null;
        foreach ($partialResponses as $partialResponse) {
            $lastPartial = $partialResponse;
        }
        return $lastPartial;
    }
}