<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Symfony;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Events\HttpResponseChunkReceived;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Class SymfonyHttpResponse
 *
 * Implements HttpResponse contract for Symfony HTTP client
 */
class SymfonyHttpResponse implements HttpResponse
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

    /**
     * Get the response status code
     *
     * @return int
     */
    #[\Override]
    public function statusCode(): int {
        return $this->response->getStatusCode();
    }

    /**
     * Get the response headers
     *
     * @return array<string, list<string>>
     */
    #[\Override]
    public function headers(): array {
        return $this->response->getHeaders();
    }

    /**
     * Get the response content
     *
     * @return string
     */
    #[\Override]
    public function body(): string {
        // workaround to handle connect timeout: https://github.com/symfony/symfony/pull/57811
        /** @noinspection LoopWhichDoesNotLoopInspection */
        foreach ($this->client->stream($this->response, $this->connectTimeout) as $chunk) {
            if ($chunk->isTimeout() && !$this->response->getInfo('connect_time')) {
                $this->response->cancel();
                throw new RuntimeException('Connect timeout');
            }
            break;
        }
        return $this->response->getContent();
    }

    /**
     * Read chunks of the stream
     *
     * @return \Generator<string>
     */
    #[\Override]
    public function stream(?int $chunkSize = null): \Generator {
        foreach ($this->client->stream($this->response, $this->connectTimeout) as $chunk) {
            if ($chunk->isTimeout()) {
                continue;
            }
            $chunk = $chunk->getContent();
            $this->events->dispatch(new HttpResponseChunkReceived($chunk));
            yield $chunk;
        }
    }

    #[\Override]
    public function isStreamed(): bool {
        return $this->isStreamed;
    }
}
