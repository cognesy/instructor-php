<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Symfony;

use Cognesy\Http\Contracts\CanAdaptHttpResponse;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Events\HttpResponseChunkReceived;
use Psr\EventDispatcher\EventDispatcherInterface;
use Cognesy\Http\Stream\IterableStream;
use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Class SymfonyHttpResponse
 *
 * Implements HttpResponse contract for Symfony HTTP client
 */
class SymfonyHttpResponseAdapter implements CanAdaptHttpResponse
{
    private ResponseInterface $response;
    private HttpClientInterface $client;
    private EventDispatcherInterface $events;
    private bool $isStreamed;

    public function __construct(
        HttpClientInterface $client,
        ResponseInterface $response,
        EventDispatcherInterface $events,
        bool $isStreamed,
        private float $connectTimeout = 1,
    ) {
        $this->client = $client;
        $this->response = $response;
        $this->events = $events;
        $this->isStreamed = $isStreamed;
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

    private function body(): string {
        // workaround to handle connect timeout: https://github.com/symfony/symfony/pull/57811
        /** @noinspection LoopWhichDoesNotLoopInspection */
        foreach ($this->client->stream($this->response, $this->connectTimeout) as $chunk) {
            if ($chunk->isTimeout() && !$this->response->getInfo('connect_time')) {
                $this->response->cancel();
                throw new RuntimeException('Connect timeout');
            }
            break;
        }
        return $this->response->getContent(false); // false = don't throw on error codes
    }

    private function stream(): \Generator {
        foreach ($this->client->stream($this->response, $this->connectTimeout) as $chunk) {
            if ($chunk->isTimeout()) {
                continue;
            }
            $chunk = $chunk->getContent();
            $this->events->dispatch(new HttpResponseChunkReceived($chunk));
            yield $chunk;
        }
    }
}
