<?php declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\HttpClient;

use Cognesy\Http\Contracts\CanAdaptHttpResponse;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Events\HttpResponseChunkReceived;
use Cognesy\Http\Stream\IterableStream;
use Illuminate\Http\Client\Response;
use Psr\EventDispatcher\EventDispatcherInterface;

class LaravelHttpResponseAdapter implements CanAdaptHttpResponse
{
    public function __construct(
        private Response $response,
        private EventDispatcherInterface $events,
        private bool $streaming = false,
        private int $streamChunkSize = 256,
    ) {}

    #[\Override]
    public function toHttpResponse(): HttpResponse
    {
        if ($this->streaming) {
            return HttpResponse::streaming(
                statusCode: $this->response->status(),
                headers: $this->response->headers(),
                stream: new IterableStream($this->stream()),
            );
        }

        return HttpResponse::sync(
            statusCode: $this->response->status(),
            headers: $this->response->headers(),
            body: $this->response->body(),
        );
    }

    /** @return \Generator<string> */
    private function stream(): \Generator
    {
        $stream = $this->response->toPsrResponse()->getBody();

        while (!$stream->eof()) {
            $chunk = $stream->read($this->streamChunkSize);
            $this->events->dispatch(new HttpResponseChunkReceived($chunk));
            yield $chunk;
        }
    }
}
