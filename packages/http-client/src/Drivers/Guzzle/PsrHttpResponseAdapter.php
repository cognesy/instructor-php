<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Guzzle;

use Cognesy\Events\Support\ListenerGate;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanAdaptHttpResponse;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Events\HttpResponseChunkReceived;
use Cognesy\Http\Events\HttpStreamCompleted;
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
    private string $requestId;
    private int $streamChunkSize;
    private ?string $cachedBody = null;

    public function __construct(
        ResponseInterface $response,
        StreamInterface $stream,
        EventDispatcherInterface $events,
        bool $isStreamed,
        string $requestId,
        int $streamChunkSize = HttpClientConfig::DEFAULT_STREAM_CHUNK_SIZE,
    ) {
        $this->response = $response;
        $this->stream = $stream;
        $this->events = $events;
        $this->isStreamed = $isStreamed;
        $this->requestId = $requestId;
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
        $bytes = 0;
        $chunkCount = 0;
        $outcome = 'abandoned';
        $error = null;
        // Resolved once per stream. See the same guard in StreamingCurlResponseAdapter
        // for why the unguarded form is expensive.
        $emitChunks = ListenerGate::wants($this->events, HttpResponseChunkReceived::class);
        try {
            while (!$this->stream->eof()) {
                $chunk = $this->stream->read($this->streamChunkSize);
                $bytes += strlen($chunk);
                $chunkCount++;
                if ($emitChunks) {
                    $this->events->dispatch(new HttpResponseChunkReceived([
                        'requestId' => $this->requestId,
                        'chunk' => $chunk,
                    ]));
                }
                yield $chunk;
            }
            $outcome = 'completed';
        } catch (\Throwable $error) {
            $outcome = 'failed';
            throw $error;
        } finally {
            $payload = [
                'requestId' => $this->requestId,
                'outcome' => $outcome,
                'bytes' => $bytes,
                'chunks' => $chunkCount,
            ];
            $payload = match (true) {
                $error !== null => [...$payload, 'error' => $error->getMessage()],
                default => $payload,
            };

            $this->events->dispatch(new HttpStreamCompleted($payload));
        }
    }
}
