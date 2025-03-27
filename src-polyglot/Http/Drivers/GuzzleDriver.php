<?php

namespace Cognesy\Polyglot\Http\Drivers;

use Cognesy\Polyglot\Http\Adapters\PsrHttpResponse;
use Cognesy\Polyglot\Http\Contracts\CanHandleHttp;
use Cognesy\Polyglot\Http\Data\HttpClientConfig;
use Cognesy\Polyglot\Http\Data\HttpClientRequest;
use Cognesy\Polyglot\Http\Contracts\HttpClientResponse;
use Cognesy\Polyglot\Http\Events\HttpRequestFailed;
use Cognesy\Polyglot\Http\Events\HttpRequestSent;
use Cognesy\Polyglot\Http\Events\HttpResponseReceived;
use Cognesy\Polyglot\Http\Exceptions\RequestException;
use Cognesy\Utils\Events\EventDispatcher;
use Exception;
use GuzzleHttp\Client;
use InvalidArgumentException;

class GuzzleDriver implements CanHandleHttp
{
    protected Client $client;

    public function __construct(
        protected HttpClientConfig $config,
        protected ?Client $httpClient = null,
        protected ?EventDispatcher $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->client = $httpClient ?? new Client();
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