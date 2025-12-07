<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\ExtHttp;

use Cognesy\Http\Contracts\CanAdaptHttpResponse;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Events\HttpResponseChunkReceived;
use Cognesy\Http\Stream\IterableStream;
use Generator;
use http\Client\Response as ExtHttpResponse;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * ExtHttpResponseAdapter
 *
 * Adapts ext-http Response objects to HttpResponse for use within the framework.
 * Handles both synchronous and streaming response patterns.
 */
class ExtHttpResponseAdapter implements CanAdaptHttpResponse
{
    private ExtHttpResponse $response;
    private EventDispatcherInterface $events;
    private bool $isStreamed;
    private int $streamChunkSize;
    private ?string $cachedBody = null;

    public function __construct(
        ExtHttpResponse $response,
        EventDispatcherInterface $events,
        bool $isStreamed,
        int $streamChunkSize = 256,
    ) {
        $this->response = $response;
        $this->events = $events;
        $this->isStreamed = $isStreamed;
        $this->streamChunkSize = $streamChunkSize;
    }

    #[\Override]
    public function toHttpResponse(): HttpResponse
    {
        if ($this->isStreamed) {
            return HttpResponse::streaming(
                statusCode: $this->response->getResponseCode(),
                headers: $this->response->getHeaders(),
                stream: new IterableStream($this->stream()),
            );
        }

        return HttpResponse::sync(
            statusCode: $this->response->getResponseCode(),
            headers: $this->response->getHeaders(),
            body: $this->body(),
        );
    }

    // INTERNAL //////////////////////////////////////////////////////////////////////////

    /**
     * Get response body as string
     */
    private function body(): string
    {
        if ($this->cachedBody === null) {
            $this->cachedBody = $this->response->getBody()->toString();
        }
        return $this->cachedBody;
    }

    /**
     * Create a generator for streaming response body chunks
     */
    private function stream(): Generator
    {
        $body = $this->response->getBody();
        $headers = $this->response->getHeaders();
        $contentLength = $headers['Content-Length'] ?? $headers['content-length'] ?? '0';
        $totalSize = is_array($contentLength) ? (int) $contentLength[0] : (int) $contentLength;
        $bytesRead = 0;

        // Convert body to string and chunk it
        $bodyString = $body->toString();
        $bodyLength = strlen($bodyString);

        for ($offset = 0; $offset < $bodyLength; $offset += $this->streamChunkSize) {
            $chunk = substr($bodyString, $offset, $this->streamChunkSize);
            $bytesRead += strlen($chunk);

            // Dispatch chunk received event
            $this->events->dispatch(new HttpResponseChunkReceived([
                'chunk' => $chunk,
                'bytesReceived' => $bytesRead,
                'totalBytes' => $totalSize > 0 ? $totalSize : $bodyLength,
            ]));

            yield $chunk;
        }
    }
}