<?php

namespace Cognesy\Instructor\Extras\Http\Adapters;

use Cognesy\Instructor\Extras\Http\Contracts\CanHandleResponse;
use Generator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class PsrResponse implements CanHandleResponse
{
    private ResponseInterface $response;
    private StreamInterface $stream;

    public function __construct(
        ResponseInterface $response,
        StreamInterface $stream,
    ) {
        $this->response = $response;
        $this->stream = $stream;
    }

    public function getContents(): string {
        return $this->response->getBody()->getContents();
    }

    public function streamContents(int $chunkSize = 1): Generator {
        while (!$this->stream->eof()) {
            yield $this->stream->read($chunkSize);
        }
    }
}
