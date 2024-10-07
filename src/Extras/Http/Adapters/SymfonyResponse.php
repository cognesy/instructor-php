<?php

namespace Cognesy\Instructor\Extras\Http\Adapters;

use Cognesy\Instructor\Extras\Http\Contracts\CanAccessResponse;
use Generator;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SymfonyResponse implements CanAccessResponse
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

    public function getStatusCode(): int {
        return $this->response->getStatusCode();
    }

    public function getHeaders(): array {
        return $this->response->getHeaders();
    }

    public function getContents(): string {
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

    public function streamContents(int $chunkSize = 1): Generator {
        foreach ($this->client->stream($this->response, $this->connectTimeout) as $chunk) {
            yield $chunk->getContent();
        }
    }
}
