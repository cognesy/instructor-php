<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Mock;

use Cognesy\Http\Contracts\CanAdaptHttpResponse;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Events\HttpResponseChunkReceived;
use Psr\EventDispatcher\EventDispatcherInterface;
use Cognesy\Http\Stream\ArrayStream;

/**
 * MockHttpResponse
 * 
 * A simple implementation of HttpResponse for testing purposes.
 */
class MockHttpResponseAdapter implements CanAdaptHttpResponse
{
    /** @var string The response body */
    private string $body;
    
    /** @var int HTTP status code */
    private int $statusCode;
    
    /** @var array HTTP response headers */
    private array $headers;
    
    /** @var string[] Optional chunks for streaming responses */
    private array $chunks;
    private bool $streaming;

    /** @var EventDispatcherInterface|null Event dispatcher for response events */
    private ?EventDispatcherInterface $events;

    /**
     * Constructor
     * 
     * @param string $body Response body
     * @param int $statusCode HTTP status code
     * @param array $headers HTTP response headers
     * @param array $chunks Optional chunks for streaming (if empty, body will be used)
     */
    public function __construct(
        int $statusCode = 200,
        array $headers = [],
        string $body = '',
        array $chunks = [],
        ?EventDispatcherInterface $events = null
    ) {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->body = $body;
        $this->streaming = !empty($chunks);
        $this->chunks = !empty($chunks) ? $chunks : [$body];
        $this->events = $events;
    }

    #[\Override]
    public function toHttpResponse() : HttpResponse {
        if ($this->isStreamed()) {
            return HttpResponse::streaming(
                statusCode: $this->statusCode(),
                headers: $this->headers(),
                stream: new ArrayStream($this->chunks),
            );
        }
        return HttpResponse::sync(
            statusCode: $this->statusCode(),
            headers: $this->headers(),
            body: $this->body(),
        );
    }

    // INTERNAL //////////////////////////////////////////////////////////////////////////

    private function statusCode(): int {
        return $this->statusCode;
    }

    /**
     * Get the response headers
     *
     * @return array
     */
    private function headers(): array {
        return $this->headers;
    }

    /**
     * Get the response body
     *
     * @return string
     */
    private function body(): string {
        return $this->body;
    }

    private function isStreamed(): bool {
        return $this->streaming;
    }

    /**
     * Read chunks of the stream
     *
     * @param int|null $chunkSize Not used in mock implementation, included for interface compatibility
     * @return \Generator<string>
     */
    private function stream(): \Generator {
        foreach ($this->chunks as $chunk) {
            $this->events?->dispatch(new HttpResponseChunkReceived($chunk));
            yield $chunk;
        }
    }
}
