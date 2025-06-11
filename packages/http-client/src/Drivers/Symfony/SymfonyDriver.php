<?php

namespace Cognesy\Http\Drivers\Symfony;

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Http\Events\HttpRequestFailed;
use Cognesy\Http\Events\HttpRequestSent;
use Cognesy\Http\Events\HttpResponseReceived;
use Cognesy\Http\Exceptions\HttpRequestException;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SymfonyDriver implements CanHandleHttpRequest
{
    protected HttpClientConfig $config;
    protected EventDispatcherInterface $events;
    protected HttpClientInterface $client;

    public function __construct(
        HttpClientConfig $config,
        ?object $clientInstance = null,
        ?EventDispatcherInterface $events = null,
    ) {
        $this->config = $config;
        $this->events = $events ?? new EventDispatcher();
        if ($clientInstance && !($clientInstance instanceof HttpClientInterface)) {
            throw new \InvalidArgumentException('Client instance of SymfonyDriver must be of type Symfony\Contracts\HttpClient\HttpClientInterface');
        }
        $this->client = $clientInstance ?? HttpClient::create(['http_version' => '2.0']);
    }

    public function handle(HttpClientRequest $request) : HttpClientResponse {
        $url = $request->url();
        $headers = $request->headers();
        $body = $request->body()->toArray();
        $method = $request->method();
        $streaming = $request->isStreamed();

        $this->events->dispatch(new HttpRequestSent([
            'url' => $url,
            'method' => $method,
            'headers' => $headers,
            'body' => $body,
        ]));

        try {
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

            // throw exception if HTTP status code => error
            if ($response->getStatusCode() >= 400) {
                $errorMessage = sprintf(
                    'HTTP request failed with status code %d: %s',
                    $response->getStatusCode(),
                    $response->getContent(false)
                );
                throw new HttpRequestException($errorMessage, $request);
            }
        } catch (Exception $e) {
            $this->events->dispatch(new HttpRequestFailed([
                'url' => $url,
                'method' => $method,
                'headers' => $headers,
                'body' => $body,
                'errors' => $e->getMessage(),
            ]));
            throw new HttpRequestException($e->getMessage(), $request, $e);
        }

        $this->events->dispatch(new HttpResponseReceived([
            'statusCode' => $response->getStatusCode()
        ]));

        return new SymfonyHttpResponse(
            client: $this->client,
            response: $response,
            isStreamed: $streaming,
            connectTimeout: $this->config->connectTimeout ?? 3,
        );
    }
}
