<?php

namespace Cognesy\Http\Adapters;

use Cognesy\Http\Contracts\HttpClientResponse;
use Generator;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Class SymfonyHttpResponse
 *
 * Implements HttpClientResponse contract for Symfony HTTP client
 */
class SymfonyHttpResponse implements HttpClientResponse
{
    private ResponseInterface $response;
    private HttpClientInterface $client;

    public function __construct(
        HttpClientInterface $client,
        ResponseInterface $response,
        private float $connectTimeout = 1,
    ) {
        $this->client = $client;
        $this->response = $response;
    }

    /**
     * Get the response status code
     *
     * @return int
     */
    public function statusCode(): int {
        return $this->response->getStatusCode();
    }

    /**
     * Get the response headers
     *
     * @return array
     */
    public function headers(): array {
        return $this->response->getHeaders();
    }

    /**
     * Get the response content
     *
     * @return string
     */
    public function body(): string {
        // workaround to handle connect timeout: https://github.com/symfony/symfony/pull/57811
        foreach ($this->client->stream($this->response, $this->connectTimeout) as $chunk) {
            if ($chunk->isTimeout() && !$this->response->getInfo('connect_time')) {
                $this->response->cancel();
                throw new \Exception('Connect timeout');
            }
            break;
        }
        return $this->response->getContent();
    }

    /**
     * Read chunks of the stream
     *
     * @param int $chunkSize
     * @return Generator<string>
     */
    public function stream(int $chunkSize = 1): Generator {
        foreach ($this->client->stream($this->response, $this->connectTimeout) as $chunk) {
            yield $chunk->getContent();
        }
    }
}
