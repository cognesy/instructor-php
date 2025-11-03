<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Curl;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Events\HttpResponseChunkReceived;
use Generator;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * HTTP response implementation for native cURL driver
 */
class CurlHttpResponse implements HttpResponse
{
    private int $statusCode;
    private array $headers;
    private string $body;
    private bool $isStreamed;
    private EventDispatcherInterface $events;
    private int $streamChunkSize;

    public function __construct(
        int $statusCode,
        array $headers,
        string $body,
        bool $isStreamed,
        EventDispatcherInterface $events,
        int $streamChunkSize = 256,
    ) {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->body = $body;
        $this->isStreamed = $isStreamed;
        $this->events = $events;
        $this->streamChunkSize = $streamChunkSize;
    }

    #[\Override]
    public function statusCode(): int {
        return $this->statusCode;
    }

    #[\Override]
    public function headers(): array {
        return $this->headers;
    }

    #[\Override]
    public function body(): string {
        return $this->body;
    }

    #[\Override]
    public function isStreamed(): bool {
        return $this->isStreamed;
    }

    #[\Override]
    public function stream(?int $chunkSize = null): Generator {
        $chunkSize = $chunkSize ?? $this->streamChunkSize;
        $offset = 0;
        $bodyLength = strlen($this->body);

        while ($offset < $bodyLength) {
            $chunk = substr($this->body, $offset, $chunkSize);
            $this->events->dispatch(new HttpResponseChunkReceived($chunk));
            yield $chunk;
            $offset += $chunkSize;
        }
    }
}
