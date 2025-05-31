<?php

namespace Cognesy\Http\Drivers;

use Cognesy\Http\Adapters\PsrHttpResponse;
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
        ?object $clientInstance = null,
        ?EventDispatcherInterface $events = null,
    ) {
        $this->config = $config;
        $this->events = $events ?? new EventDispatcher();
        if ($clientInstance && !($clientInstance instanceof ClientInterface)) {
            throw new \InvalidArgumentException('Client instance of GuzzleDriver must be of type GuzzleHttp\ClientInterface');
        }
        $this->client = $clientInstance ?? new Client();
    }

    public function handle(HttpClientRequest $request) : HttpClientResponse {
        $url = $request->url();
        $headers = $request->headers();
        $body = $request->body()->toArray();
        $method = $request->method();
        $streaming = $request->isStreamed();
        
        $this->events->dispatch(new HttpRequestSent($url, $method, $headers, $body));
        
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
            $this->events->dispatch(new HttpRequestFailed(
                $url,
                $method,
                $headers,
                $body,
                $message,
            ));

            // Optionally, include response content in the thrown exception
            throw new RequestException(message: $message);
        } catch (Exception $e) {
            $this->events->dispatch(new HttpRequestFailed($url, $method, $headers, $body, $e->getMessage()));
            throw new RequestException($e);
        }
        
        $this->events->dispatch(new HttpResponseReceived($response->getStatusCode()));
        return new PsrHttpResponse(
            response: $response,
            stream: $response->getBody()
        );
    }
}