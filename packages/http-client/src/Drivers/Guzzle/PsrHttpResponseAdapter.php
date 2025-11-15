<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Guzzle;

use Cognesy\Http\Contracts\CanAdaptHttpResponse;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Events\HttpResponseChunkReceived;
use Cognesy\Http\Stream\IterableStream;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Class PsrHttpResponse
 *
 * Implements HttpResponse contract for PSR-compatible HTTP client
 */
class PsrHttpResponseAdapter implements CanAdaptHttpResponse
{
    private ResponseInterface $response;
    private StreamInterface $stream;
    private EventDispatcherInterface $events;
    private bool $isStreamed;
    private int $streamChunkSize;
    private ?string $cachedBody = null;

    public function __construct(
        ResponseInterface $response,
        StreamInterface $stream,
        EventDispatcherInterface $events,
        bool $isStreamed,
        int $streamChunkSize = 256,
    ) {
        $this->response = $response;
        $this->stream = $stream;
        $this->events = $events;
        $this->isStreamed = $isStreamed;
        $this->streamChunkSize = $streamChunkSize;
    }

    #[\Override]
    public function toHttpResponse() : HttpResponse {
        if ($this->isStreamed) {
            return HttpResponse::streaming(
                statusCode: $this->response->getStatusCode(),
                headers: $this->response->getHeaders(),
                stream: new IterableStream($this->stream()),
            );
        }
        return HttpResponse::sync(
            statusCode: $this->response->getStatusCode(),
            headers: $this->response->getHeaders(),
            body: $this->body(),
        );
    }

    // INTERNAL //////////////////////////////////////////////////////////////////////////

    private function statusCode(): int {
        return $this->response->getStatusCode();
    }

    /**
     * Get the response headers
     *
     * @return array<string, array<string>>
     */
    private function headers(): array {
        return $this->response->getHeaders();
    }

    /**
     * Get the response content
     *
     * @return string
     */
    private function body(): string {
        if ($this->cachedBody === null) {
            $body = $this->response->getBody();
            // Only rewind if the stream is seekable (non-streaming responses)
            if ($body->isSeekable()) {
                $body->rewind();
            }
            $this->cachedBody = $body->getContents();
        }
        return $this->cachedBody;
    }

    /**
     * Read chunks of the stream
     *
     * @return \Generator<string>
     */
    private function stream(): \Generator {
        while (!$this->stream->eof()) {
            $chunk = $this->stream->read($this->streamChunkSize);
            $this->events->dispatch(new HttpResponseChunkReceived($chunk));
            yield $chunk;
        }
    }

    private function isStreamed(): bool {
        return $this->isStreamed;
    }
}
