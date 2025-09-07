<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Guzzle;

use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Events\HttpRequestFailed;
use Cognesy\Http\Events\HttpRequestSent;
use Cognesy\Http\Events\HttpResponseReceived;
use Cognesy\Http\Exceptions\ConnectionException;
use Cognesy\Http\Exceptions\HttpExceptionFactory;
use Cognesy\Http\Exceptions\NetworkException;
use Cognesy\Http\Exceptions\TimeoutException;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException as GuzzleConnectException;
use GuzzleHttp\Exception\GuzzleException;
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
        $startTime = microtime(true);
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
                'http_errors' => false, // Disable Guzzle's automatic HTTP error handling
            ]);
        } catch (GuzzleConnectException $e) {
            $duration = microtime(true) - $startTime;
            $message = $e->getMessage();

            if (str_contains($message, 'timeout') || str_contains($message, 'timed out')) {
                $httpException = new TimeoutException($message, $request, $duration, $e);
            } else {
                $httpException = new ConnectionException($message, $request, $duration, $e);
            }

            $this->events->dispatch(new HttpRequestFailed([
                'url' => $url,
                'method' => $method,
                'headers' => $headers,
                'body' => $body,
                'errors' => $httpException->getMessage(),
                'duration' => $duration,
            ]));

            throw $httpException;
        } catch (GuzzleException $e) {
            $duration = microtime(true) - $startTime;
            $httpException = new NetworkException($e->getMessage(), $request, null, $duration, $e);

            $this->events->dispatch(new HttpRequestFailed([
                'url' => $url,
                'method' => $method,
                'headers' => $headers,
                'body' => $body,
                'errors' => $httpException->getMessage(),
                'duration' => $duration,
            ]));

            throw $httpException;
        }
        
        // Check for HTTP status errors (if failOnError is enabled)
        $duration = microtime(true) - $startTime;
        if ($this->config->failOnError && $response->getStatusCode() >= 400) {
            $httpResponse = new PsrHttpResponse(
                response: $response,
                stream: $response->getBody(),
                events: $this->events,
                isStreamed: $streaming,
                streamChunkSize: $this->config->streamChunkSize,
            );
            
            $httpException = HttpExceptionFactory::fromStatusCode(
                $response->getStatusCode(),
                $request,
                $httpResponse,
                $duration
            );
            
            $this->events->dispatch(new HttpRequestFailed([
                'url' => $url,
                'method' => $method,
                'statusCode' => $response->getStatusCode(),
                'duration' => $duration,
            ]));
            
            throw $httpException;
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