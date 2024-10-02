<?php

namespace Cognesy\Instructor\Extras\Http\Drivers;

use Cognesy\Instructor\Extras\Debug\Debug;
use Cognesy\Instructor\Extras\Http\Contracts\CanHandleHttp;
use Cognesy\Instructor\Extras\Http\Data\HttpClientConfig;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;

// TODO: test me
// TODO: add debugging support
class SymfonyHttpClient implements CanHandleHttp
{
    protected Psr18Client $client;

    public function __construct(
        protected HttpClientConfig $config,
        protected ?HttpClient $httpClient = null,
    ) {
        $this->client = new Psr18Client(client: $httpClient);
    }

    public function handle(
        string $url,
        array $headers,
        array $body,
        string $method = 'POST',
        bool $streaming = false
    ) : ResponseInterface {
        $request = $this->client->withOptions([
            'headers' => $headers,
            'json' => $body,
            'connect_timeout' => $this->config->connectTimeout ?? 3,
            'timeout' => $this->config->requestTimeout ?? 30,
            'debug' => Debug::isFlag('http.trace') ?? false,
            'stream' => $streaming,
        ])->createRequest($method, $url);
        return $this->client->sendRequest($request);
    }
}