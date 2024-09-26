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

    public function toText() : string {
        return match($this->isStreamed) {
            false => $this->toApiResponse()->content,
            true => '', // TODO: FIX ME
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
        return match($this->isStreamed) {
            false => $this->driver->toApiResponse(Json::parse($this->response->getBody()->getContents())),
            true => '', // TODO: FIX ME
        };
    }

    /**
     * @return Generator<PartialApiResponse>
     */
    public function toPartialApiResponses() : Generator {
        $stream = $this->streamIterator($this->psrStream());
        foreach ($stream as $partialData) {
            yield $this->driver->toPartialApiResponse(Json::parse($partialData, default: []));
        }
    }

    // LOW LEVEL ACCESS /////////////////////////////////////////

    public function asArray() : array {
        return match($this->isStreamed) {
            false => Json::parse($this->response->getBody()->getContents()),
            true => $this->allStreamResponses(),
        };
    }

    public function psrResponse() : ResponseInterface {
        return $this->response;
    }

    public function psrStream(bool $asCachingStream = true) : StreamInterface {
        return match($asCachingStream) {
            false => $this->response->getBody(),
            true => new CachingStream($this->response->getBody()),
        };
    }

    // INTERNAL /////////////////////////////////////////////////

    /**
     * @return Generator<string>
     */
    protected function streamIterator(StreamInterface $stream): Generator {
        if (!$this->isStreamed) {
            throw new InvalidArgumentException('Trying to read partial responses with no streaming');
        }

        while (!$stream->eof()) {
            if (($line = trim($this->readLine($stream))) === '') {
                continue;
            }
            if (($data = $this->driver->getData($line)) === false) {
                break;
            }
            yield $data;
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

    protected function allStreamResponses() : array {
        $stream = $this->streamIterator($this->psrStream());
        $content = [];
        foreach ($stream as $partialData) {
            $content[] = Json::parse($partialData);
        }
        return $content;
    }
}