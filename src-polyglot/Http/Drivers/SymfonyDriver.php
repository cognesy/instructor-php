<?php

namespace Cognesy\Polyglot\Http\Drivers;

use Cognesy\Polyglot\Http\Adapters\SymfonyHttpResponse;
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
        $this->client = $httpClient ?? HttpClient::create(['http_version' => '2.0']);
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

    // INTERNAL /////////////////////////////////////////////////

}
