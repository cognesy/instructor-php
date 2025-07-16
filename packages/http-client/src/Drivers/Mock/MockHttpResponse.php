<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Mock;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Events\HttpResponseChunkReceived;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * MockHttpResponse
 * 
 * A simple implementation of HttpResponse for testing purposes.
 */
class MockHttpResponse implements HttpResponse
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
        EventDispatcherInterface $events = null
    ) {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->body = $body;
        $this->streaming = !empty($chunks);
        $this->chunks = !empty($chunks) ? $chunks : [$body];
        $this->events = $events;
    }

    /**
     * Static factory to create a successful response
     */
    public static function success(int $statusCode = 200, array $headers = [], string $body = '', array $chunks = []): self {
        return new self($statusCode, $headers, $body, $chunks);
    }

    /**
     * Static factory to create an error response
     */
    public static function error(int $statusCode = 500, array $headers = [], string $body = '', array $chunks = []): self {
        return new self($statusCode, $headers, $body, $chunks);
    }

    /**
     * Static factory to create a streaming response
     */
    public static function streaming(int $statusCode = 200, array $headers = [], array $chunks = []): self {
        return new self($statusCode, $headers, implode('', $chunks), $chunks);
    }

    /**
     * Get the response status code
     * 
     * @return int
     */
    public function statusCode(): int {
        return $this->statusCode;
    }

    /**
     * Get the response headers
     * 
     * @return array
     */
    public function headers(): array {
        return $this->headers;
    }

    /**
     * Get the response body
     * 
     * @return string
     */
    public function body(): string {
        return $this->body;
    }

    public function isStreamed(): bool {
        return $this->streaming;
    }

    /**
     * Read chunks of the stream
     * 
     * @param int $chunkSize Not used in mock implementation, included for interface compatibility
     * @return iterable<string>
     */
    public function stream(?int $chunkSize = null): iterable {
        foreach ($this->chunks as $chunk) {
            $this->events?->dispatch(new HttpResponseChunkReceived($chunk));
            yield $chunk;
        }
    }
}
