<?php

namespace Cognesy\Http\Drivers\Laravel;

use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Events\HttpRequestFailed;
use Cognesy\Http\Events\HttpRequestSent;
use Cognesy\Http\Events\HttpResponseReceived;
use Cognesy\Http\Exceptions\HttpRequestException;
use Exception;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;

class LaravelDriver implements CanHandleHttpRequest
{
    protected HttpClientConfig $config;
    protected EventDispatcherInterface $events;
    protected HttpFactory $factory;

    public function __construct(
        HttpClientConfig $config,
        EventDispatcherInterface $events,
        ?object $clientInstance = null,
    ) {
        $this->config = $config;
        $this->events = $events;
        if ($clientInstance && !($clientInstance instanceof HttpFactory)) {
            throw new \InvalidArgumentException('Client instance of LaravelDriver must be of type Illuminate\Http\Client\Factory');
        }
        $this->factory = $clientInstance ?? new HttpFactory();
    }

    public function handle(HttpRequest $request): HttpResponse {
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

        // Create a fresh pending request with configuration
        $pendingRequest = $this->factory
            ->timeout($this->config->requestTimeout)
            ->connectTimeout($this->config->connectTimeout)
            ->withHeaders($headers);

        if ($streaming) {
            $pendingRequest->withOptions(['stream' => true]);
        }

        try {
            // Send the request based on the method
            $response = $this->sendRequest($pendingRequest, $method, $url, $body);
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
            'statusCode' => $response->status()
        ]));
        return new LaravelHttpResponse(
            response: $response,
            events: $this->events,
            streaming: $streaming,
            streamChunkSize: $this->config->streamChunkSize,
        );
    }

    // INTERNAL /////////////////////////////////////////////

    private function sendRequest(PendingRequest $pendingRequest, string $method, string $url, array $body): Response {
        return match (strtoupper($method)) {
            'GET' => $pendingRequest->get($url),
            'POST' => $pendingRequest->post($url, $body),
            'PUT' => $pendingRequest->put($url, $body),
            'PATCH' => $pendingRequest->patch($url, $body),
            'DELETE' => $pendingRequest->delete($url, $body),
            default => throw new InvalidArgumentException("Unsupported HTTP method: {$method}")
        };
    }
}