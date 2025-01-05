<?php

namespace Cognesy\Instructor\Features\Http\Adapters;

use Cognesy\Instructor\Features\Http\Contracts\CanAccessResponse;
use Generator;
use Illuminate\Http\Client\Response;

class LaravelResponse implements CanAccessResponse
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