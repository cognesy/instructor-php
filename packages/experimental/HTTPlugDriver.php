<?php

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientConfig;
use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Http\Events\HttpRequestFailed;
use Cognesy\Http\Events\HttpRequestSent;
use Cognesy\Http\Events\HttpResponseReceived;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Utils\Events\EventDispatcher;
use Http\Client\HttpClient;
use Http\Discovery\HttpClientDiscovery;
use Http\Discovery\Psr17FactoryDiscovery;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\RequestInterface;

class HTTPlugDriver implements CanHandleHttpRequest
{
    protected HttpClientConfig $config;
    protected EventDispatcherInterface $events;
    protected HttpClient $client;
    protected $requestFactory;
    protected $streamFactory;

    public function __construct(
        HttpClientConfig $config,
        ?HttpClient $clientInstance = null,
        ?EventDispatcherInterface $events = null,
    ) {
        $this->config = $config;
        $this->events = $events ?? new EventDispatcher();
        $this->client = $clientInstance ?? HttpClientDiscovery::find();
        $this->requestFactory = Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory = Psr17FactoryDiscovery::findStreamFactory();
    }

    public function handle(HttpClientRequest $request): HttpClientResponse
    {
        $url = $request->url();
        $headers = $request->headers();
        $body = $request->body()->toArray();
        $method = $request->method();
        $streaming = $request->isStreamed();

        // Dispatch request sent event
        $this->events->dispatch(new HttpRequestSent($url, $method, $headers, $body));

        // Convert custom HttpClientRequest to PSR-7 RequestInterface
        $psrRequest = $this->createPsrRequest($method, $url, $headers, $body);

        try {
            // Send the request with HTTPlug client
            $response = $this->client->sendRequest($psrRequest, [
                'connect_timeout' => $this->config->connectTimeout ?? 3,
                'timeout' => $this->config->requestTimeout ?? 30,
                'stream' => $streaming,
            ]);

            // Dispatch response received event
            $this->events->dispatch(new HttpResponseReceived($response->getStatusCode()));

            // Return response wrapped in PsrHttpResponse
            return new \Cognesy\Http\Drivers\PsrHttpResponse(
                response: $response,
                stream: $response->getBody()
            );
        } catch (\Exception $e) {
            // Dispatch request failed event
            $message = $e->getMessage();
            $this->events->dispatch(new HttpRequestFailed($url, $method, $headers, $body, $message));

            // Throw custom exception
            throw new HttpRequestException($message, $request, $e);
        }
    }

    /**
     * Convert HttpClientRequest to PSR-7 RequestInterface
     */
    private function createPsrRequest(string $method, string $url, array $headers, array $body): RequestInterface
    {
        $psrRequest = $this->requestFactory->createRequest($method, $url);

        // Add headers
        foreach ($headers as $name => $value) {
            $psrRequest = $psrRequest->withHeader($name, $value);
        }

        // Add body if present
        if (!empty($body)) {
            $stream = $this->streamFactory->createStream(json_encode($body));
            $psrRequest = $psrRequest->withHeader('Content-Type', 'application/json')
                                     ->withBody($stream);
        }

        return $psrRequest;
    }
}