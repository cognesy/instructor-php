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
        return $this->response->getContent();
    }

    public function streamContents(int $chunkSize = 1): Generator {
        foreach ($this->client->stream($this->response) as $chunk) {
            yield $chunk->getContent();
        }
    }
}
