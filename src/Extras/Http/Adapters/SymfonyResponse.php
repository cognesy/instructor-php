<?php

namespace Cognesy\Instructor\Extras\Http\Adapters;

use Cognesy\Instructor\Extras\Http\Contracts\CanHandleResponse;
use Generator;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SymfonyResponse implements CanHandleResponse
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

    public function getContents(): string {
        return $this->response->getContent();
    }

    public function streamContents(int $chunkSize = 1): Generator {
        foreach ($this->client->stream($this->response) as $chunk) {
            yield $chunk->getContent();
        }
    }
}
