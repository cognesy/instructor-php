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
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;

class GuzzleDriver implements CanHandleHttpRequest
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
        } catch (GuzzleRequestException $e) {
            // Get the response from the exception, if available
            $responseContent = null;
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $responseContent = $response->getBody()->getContents();
            }

            // Dispatch event with full error details
            $this->events->dispatch(new HttpRequestFailed(
                $url,
                $method,
                $headers,
                $body,
                $e->getMessage(),
                $responseContent // Pass the response content to the event
            ));

            // Optionally, include response content in the thrown exception
            throw new RequestException($e);
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