<?php

namespace Cognesy\Http\Drivers;

use Cognesy\Http\Adapters\SymfonyHttpResponse;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientConfig;
use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Http\Events\HttpRequestFailed;
use Cognesy\Http\Events\HttpRequestSent;
use Cognesy\Http\Events\HttpResponseReceived;
use Cognesy\Http\Exceptions\RequestException;
use Cognesy\Utils\Events\EventDispatcher;
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
            throw new \InvalidArgumentException('Client instance must be of type Symfony\Contracts\HttpClient\HttpClientInterface');
        }
        $this->client = $clientInstance ?? HttpClient::create(['http_version' => '2.0']);
    }

    public function handle(HttpClientRequest $request) : HttpClientResponse {
        $url = $request->url();
        $headers = $request->headers();
        $body = $request->body()->toArray();
        $method = $request->method();
        $streaming = $request->isStreamed();

        $this->events->dispatch(new HttpRequestSent($url, $method, $headers, $body));
        try {
            //Debug::tryDumpUrl($url);
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
            $this->events->dispatch(new HttpRequestFailed($url, $method, $headers, $body, $e->getMessage()));
            throw new RequestException($e);
        }
        $this->events->dispatch(new HttpResponseReceived($response->getStatusCode()));
        return new SymfonyHttpResponse(
            client: $this->client,
            response: $response,
            connectTimeout: $this->config->connectTimeout ?? 3,
        );
    }
}
