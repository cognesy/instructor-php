<?php

namespace Cognesy\Instructor\Features\Http\Adapters;

use Cognesy\Instructor\Features\Http\Contracts\ResponseAdapter;
use Generator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class PsrResponseAdapter implements ResponseAdapter
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

    public function getStatusCode(): int {
        return $this->response->getStatusCode();
    }

    public function getHeaders(): array {
        return $this->response->getHeaders();
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
