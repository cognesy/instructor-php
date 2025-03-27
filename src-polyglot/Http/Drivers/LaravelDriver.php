<?php

namespace Cognesy\Polyglot\Http\Drivers;

use Cognesy\Polyglot\Http\Adapters\LaravelHttpResponse;
use Cognesy\Polyglot\Http\Contracts\CanHandleHttp;
use Cognesy\Polyglot\Http\Data\HttpClientConfig;
use Cognesy\Polyglot\Http\Data\HttpClientRequest;
use Cognesy\Polyglot\Http\Contracts\HttpClientResponse;
use Cognesy\Polyglot\Http\Events\HttpRequestFailed;
use Cognesy\Polyglot\Http\Events\HttpRequestSent;
use Cognesy\Polyglot\Http\Events\HttpResponseReceived;
use Cognesy\Polyglot\Http\Exceptions\RequestException;
use Cognesy\Utils\Debug\Debug;
use Cognesy\Utils\Events\EventDispatcher;
use Exception;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use InvalidArgumentException;

class LaravelDriver implements CanHandleHttp
{
    private HttpFactory $factory;

    public function __construct(
        protected HttpClientConfig $config,
        protected ?HttpFactory     $httpClient = null,
        protected ?EventDispatcher $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->factory = $httpClient ?? new HttpFactory();
    }

    public function handle(HttpClientRequest $request): HttpClientResponse {
        $url = $request->url();
        $headers = $request->headers();
        $body = $request->body()->toArray();
        $method = $request->method();
        $streaming = $request->isStreamed();

        $this->events->dispatch(new HttpRequestSent($url, $method, $headers, $body));
        //Debug::tryDumpUrl($url);

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
            $this->events->dispatch(new HttpRequestFailed($url, $method, $headers, $body, $e->getMessage()));
            throw new RequestException($e);
        }
        $this->events->dispatch(new HttpResponseReceived($response->status()));
        return new LaravelHttpResponse(
            $response,
            $streaming
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