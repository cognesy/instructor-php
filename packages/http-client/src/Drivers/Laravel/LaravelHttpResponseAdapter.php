<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Laravel;

use Cognesy\Http\Contracts\CanAdaptHttpResponse;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Events\HttpResponseChunkReceived;
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
        return new HttpResponse(
            statusCode: $this->response->status(),
            body: $this->streaming ? '' : $this->body(),
            headers: $this->response->headers(),
            isStreamed: $this->isStreamed(),
            stream: $this->stream(),
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
