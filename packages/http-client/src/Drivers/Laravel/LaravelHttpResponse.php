<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Laravel;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Events\HttpResponseChunkReceived;
use Illuminate\Http\Client\Response;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Class LaravelHttpResponse
 *
 * Implements HttpResponse contract for Laravel HTTP client
 */
class LaravelHttpResponse implements HttpResponse
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
    public function statusCode(): int {
        return $this->response->status();
    }

    /**
     * Get the response headers
     *
     * @return array<string, string>
     */
    #[\Override]
    public function headers(): array {
        return $this->response->headers();
    }

    #[\Override]
    public function body(): string {
        return $this->response->body();
    }

    #[\Override]
    public function isStreamed(): bool {
        return $this->streaming;
    }

    /**
     * Read chunks of the stream
     *
     * @param int|null $chunkSize
     * @return \Generator<string>
     */
    #[\Override]
    public function stream(?int $chunkSize = null): \Generator {
        //if (!$this->streaming) {
        //    $chunk = $this->body();
        //    $this->events->dispatch(new HttpResponseChunkReceived($chunk));
        //    yield $chunk;
        //    return;
        //}

        $stream = $this->response->toPsrResponse()->getBody();
        while (!$stream->eof()) {
            $chunk = $stream->read($chunkSize ?? $this->streamChunkSize);
            $this->events->dispatch(new HttpResponseChunkReceived($chunk));
            yield $chunk;
        }
    }
}