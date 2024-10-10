<?php

namespace Cognesy\Instructor\Features\Http\Drivers;

use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\HttpClient\RequestSentToLLM;
use Cognesy\Instructor\Events\HttpClient\RequestToLLMFailed;
use Cognesy\Instructor\Events\HttpClient\ResponseReceivedFromLLM;
use Cognesy\Instructor\Features\Http\Adapters\SymfonyResponse;
use Cognesy\Instructor\Features\Http\Contracts\CanAccessResponse;
use Cognesy\Instructor\Features\Http\Contracts\CanHandleHttp;
use Cognesy\Instructor\Features\Http\Data\HttpClientConfig;
use Cognesy\Instructor\Utils\Debug\Debug;
use Exception;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SymfonyDriver implements CanHandleHttp
{
    private HttpClientInterface $client;

    public function __construct(
        protected HttpClientConfig $config,
        protected ?HttpClientInterface $httpClient = null,
        protected ?EventDispatcher $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->client = $httpClient ?? HttpClient::create();
    }

    public function handle(
        string $url,
        array $headers,
        array $body,
        string $method = 'POST',
        bool $streaming = false,
    ): CanAccessResponse {
        $this->events->dispatch(new RequestSentToLLM($url, $method, $headers, $body));
        try {
            Debug::tryDumpUrl($url);
            $response = $this->client->request(
                method: $method,
                url: $url,
                options: [
                    'headers' => $headers,
                    'body' => is_array($body) ? json_encode($body) : $body,
                    'timeout' => $this->config->idleTimeout ?? 0,
                    'max_duration' => $this->config->requestTimeout ?? 30,
                    'buffer' => !$streaming,
                ]
            );
        } catch (Exception $e) {
            $this->events->dispatch(new RequestToLLMFailed($url, $method, $headers, $body, $e->getMessage()));
            throw $e;
        }
        $this->events->dispatch(new ResponseReceivedFromLLM($response->getStatusCode()));
        return new SymfonyResponse(
            client: $this->client,
            response: $response,
            connectTimeout: $this->config->connectTimeout ?? 3,
        );
    }
}
