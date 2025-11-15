<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Laravel;

use Cognesy\Http\Contracts\CanAdaptHttpResponse;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Events\HttpResponseChunkReceived;
use Cognesy\Http\Stream\IterableStream;
use Illuminate\Http\Client\Response;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Class LaravelHttpResponse
 *
 * Implements HttpResponse contract for Laravel HTTP client
 */
class LaravelHttpResponseAdapter implements CanAdaptHttpResponse
{
    private Response $response;
    private EventDispatcherInterface $events;
    private bool $streaming;
    private int $streamChunkSize;

    public function __construct(
        Response $response,
        EventDispatcherInterface $events,
        bool $streaming = false,
        int $streamChunkSize = 256,
    ) {
        $this->response = $response;
        $this->events = $events;
        $this->streaming = $streaming;
        $this->streamChunkSize = $streamChunkSize;
    }

    #[\Override]
    public function toHttpResponse() : HttpResponse {
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
            body: $this->body(),
        );
    }

    // INTERNAL //////////////////////////////////////////////////////////////////////////

    private function statusCode(): int {
        return $this->response->status();
    }

    /**
     * Get the response headers
     *
     * @return array<string, string>
     */
    private function headers(): array {
        return $this->response->headers();
    }

    private function body(): string {
        return $this->response->body();
    }

    private function isStreamed(): bool {
        return $this->streaming;
    }

    /**
     * Read chunks of the stream using configured chunk size.
     *
     * @return \Generator<string>
     */
    private function stream(): \Generator {
        //if (!$this->streaming) {
        //    $chunk = $this->body();
        //    $this->events->dispatch(new HttpResponseChunkReceived($chunk));
        //    yield $chunk;
        //    return;
        //}

        $stream = $this->response->toPsrResponse()->getBody();
        while (!$stream->eof()) {
            $chunk = $stream->read($this->streamChunkSize);
            $this->events->dispatch(new HttpResponseChunkReceived($chunk));
            yield $chunk;
        }
    }
}
