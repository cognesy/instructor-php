<?php

namespace Cognesy\Http\Drivers\Symfony;

use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Events\HttpRequestFailed;
use Cognesy\Http\Events\HttpRequestSent;
use Cognesy\Http\Events\HttpResponseReceived;
use Cognesy\Http\Exceptions\HttpRequestException;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpClient\HttpClient as SymfonyHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SymfonyDriver implements CanHandleHttpRequest
{
    protected HttpClientConfig $config;
    protected EventDispatcherInterface $events;
    protected HttpClientInterface $client;

    public function __construct(
        HttpClientConfig $config,
        EventDispatcherInterface $events,
        ?object $clientInstance = null,
    ) {
        $this->config = $config;
        $this->events = $events;
        if ($clientInstance && !($clientInstance instanceof HttpClientInterface)) {
            throw new \InvalidArgumentException('Client instance of SymfonyDriver must be of type Symfony\Contracts\HttpClient\HttpClientInterface');
        }
        $this->client = $clientInstance ?? SymfonyHttpClient::create(['http_version' => '2.0']);
    }

    public function handle(HttpRequest $request) : HttpResponse {
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
                    'timeout' => $this->config->idleTimeout,
                    'max_duration' => $this->config->requestTimeout,
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
            events: $this->events,
            isStreamed: $streaming,
            connectTimeout: $this->config->connectTimeout,
        );
    }
}
