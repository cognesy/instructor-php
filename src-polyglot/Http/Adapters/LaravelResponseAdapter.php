<?php

namespace Cognesy\Polyglot\Http\Adapters;

use Cognesy\Polyglot\Http\Contracts\ResponseAdapter;
use Generator;
use Illuminate\Http\Client\Response;

class LaravelResponseAdapter implements ResponseAdapter
{
    public function __construct(
        private Response $response,
        private bool $streaming = false
    ) {}

    public function getStatusCode(): int
    {
        return $this->response->status();
    }

    public function getHeaders(): array
    {
        return $this->response->headers();
    }

    public function getContents(): string
    {
        return $this->response->body();
    }

    public function streamContents(int $chunkSize = 1): Generator
    {
        if (!$this->streaming) {
            yield $this->getContents();
            return;
        }

        $stream = $this->response->toPsrResponse()->getBody();
        while (!$stream->eof()) {
            yield $stream->read($chunkSize);
        }
    }

//    public function streamContents(int $chunkSize = 1): Generator
//    {
//        if (!$this->streaming) {
//            yield $this->getContents();
//            return;
//        }
//
//        $resource = StreamWrapper::getResource($this->response->toPsrResponse()->getBody());
//
//        while (!feof($resource)) {
//            yield fread($resource, $chunkSize);
//        }
//
//        fclose($resource);
//    }
}