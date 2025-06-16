<?php

namespace Cognesy\Http\Drivers\Guzzle;

use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Events\HttpRequestFailed;
use Cognesy\Http\Events\HttpRequestSent;
use Cognesy\Http\Events\HttpResponseReceived;
use Cognesy\Http\Exceptions\HttpRequestException;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use Psr\EventDispatcher\EventDispatcherInterface;

class GuzzleDriver implements CanHandleHttpRequest
{
    protected HttpClientConfig $config;
    protected EventDispatcherInterface $events;
    protected ClientInterface $client;

    public function __construct(
        HttpClientConfig $config,
        EventDispatcherInterface $events,
        ?object $clientInstance = null,
    ) {
        $this->config = $config;
        $this->events = $events;
        if ($clientInstance && !($clientInstance instanceof ClientInterface)) {
            throw new \InvalidArgumentException('Client instance of GuzzleDriver must be of type GuzzleHttp\ClientInterface');
        }
        $this->client = $clientInstance ?? new Client();
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
            $response = $this->client->request($method, $url, [
                'headers' => $headers,
                'json' => $body,
                'connect_timeout' => $this->config->connectTimeout ?? 3,
                'timeout' => $this->config->requestTimeout ?? 30,
                'stream' => $streaming,
            ]);
        } catch (GuzzleRequestException $e) {
            // Get the response from the exception, if available
            $message = match(true) {
                $e->hasResponse() && $e->getResponse() => (string) $e->getResponse()?->getBody(),
                default => $e->getMessage(),
            };

            // Dispatch event with full error details
            $this->events->dispatch(new HttpRequestFailed([
                'url' => $url,
                'method' => $method,
                'headers' => $headers,
                'body' => $body,
                'errors' => $message,
            ]));
            // Optionally, include response content in the thrown exception
            throw new HttpRequestException(message: $message, request: $request, previous: $e);
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
        return new PsrHttpResponse(
            response: $response,
            stream: $response->getBody(),
            events: $this->events,
            isStreamed: $streaming,
            streamChunkSize: $this->config->streamChunkSize,
        );
    }
}