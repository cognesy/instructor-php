<?php

namespace Cognesy\Instructor\Extras\LLM;

use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Cognesy\Instructor\Extras\LLM\Data\LLMConfig;
use Cognesy\Instructor\Extras\LLM\Contracts\CanHandleInference;
use Cognesy\Instructor\Utils\Json\Json;
use Generator;
use GuzzleHttp\Psr7\CachingStream;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class InferenceResponse
{
    public function __construct(
        protected ResponseInterface  $response,
        protected CanHandleInference $driver,
        protected LLMConfig          $config,
        protected bool               $isStreamed = false,
    ) {}

    public function isStreamed() : bool {
        return $this->isStreamed;
    }

    public function toResponse() : ResponseInterface {
        return $this->response;
    }

    public function toArray() : array {
        return Json::parse($this->response->getBody()->getContents());
    }

    public function toText() : string {
        return $this->toApiResponse()->content;
    }

    public function toApiResponse() : ApiResponse {
        return $this->driver->toApiResponse($this->toArray());
    }

    /**
     * @return Generator<PartialApiResponse>
     */
    public function toPartialApiResponses() : Generator {
        $stream = $this->streamIterator($this->getCachingStream());
        foreach ($stream as $partialData) {
            yield $this->driver->toPartialApiResponse(Json::parse($partialData, default: []));
        }
    }

    /**
     * @return Generator<string>
     */
    public function toStream() : Generator {
        $stream = $this->toPartialApiResponses();
        foreach ($stream as $partialApiResponse) {
            yield $partialApiResponse->delta;
        }
    }

    // INTERNAL /////////////////////////////////////////////////

    protected function getCachingStream() : StreamInterface {
        return new CachingStream($this->response->getBody());
    }

    /**
     * @return Generator<string>
     */
    protected function streamIterator(StreamInterface $stream): Generator {
        if (!$this->isStreamed) {
            throw new InvalidArgumentException('Trying to read partial responses with no streaming');
        }
        while (!$stream->eof()) {
            $line = trim($this->readLine($stream));
            if (empty($line)) {
                continue;
            }
            if ($this->driver->isDone($line)) {
                break;
            }
            yield $this->driver->getData($line);
        }
    }

    protected function readLine(StreamInterface $stream): string {
        $buffer = '';
        while (!$stream->eof()) {
            if ('' === ($byte = $stream->read(1))) {
                return $buffer;
            }
            $buffer .= $byte;
            if ($byte === "\n") {
                break;
            }
        }
        return $buffer;
    }
}