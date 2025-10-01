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
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Http\Exceptions\NetworkException;
use Cognesy\Http\Exceptions\TimeoutException;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException as GuzzleConnectException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;

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
        $this->dispatchRequestSent($request);
        try {
            $response = $this->performHttpCall($request);
        } catch (GuzzleConnectException $e) {
            $this->handleConnectionException($e, $request, $startTime);
        } catch (GuzzleException $e) {
            $this->handleNetworkException($e, $request, $startTime);
        }
        $this->validateStatusCodeOrFail($response, $request, $startTime);
        $this->dispatchResponseReceived($response);
        return $this->buildHttpResponse($response, $request);
    }

    // INTERNAL /////////////////////////////////////////////

    private function dispatchRequestSent(HttpRequest $request): void {
        $this->events->dispatch(new HttpRequestSent([
            'url' => $request->url(),
            'method' => $request->method(),
            'headers' => $request->headers(),
            'body' => $request->body()->toArray(),
        ]));
    }

    private function performHttpCall(HttpRequest $request) : ResponseInterface {
        return $this->client->request($request->method(), $request->url(), [
            'headers' => $request->headers(),
            'json' => $request->body()->toArray(),
            'connect_timeout' => $this->config->connectTimeout ?? 3,
            'timeout' => $this->config->requestTimeout ?? 30,
            'stream' => $request->isStreamed(),
            'http_errors' => false, // Disable Guzzle's automatic HTTP error handling
        ]);
    }

    private function handleConnectionException(GuzzleConnectException $e, HttpRequest $request, float $startTime): never {
        $duration = microtime(true) - $startTime;
        $message = $e->getMessage();

        $httpException = str_contains($message, 'timeout') || str_contains($message, 'timed out')
            ? new TimeoutException($message, $request, $duration, $e)
            : new ConnectionException($message, $request, $duration, $e);

        $this->dispatchRequestFailed($httpException, $request, $duration);
        throw $httpException;
    }

    private function handleNetworkException(GuzzleException $e, HttpRequest $request, float $startTime): never {
        $duration = microtime(true) - $startTime;
        $httpException = new NetworkException($e->getMessage(), $request, null, $duration, $e);

        $this->dispatchRequestFailed($httpException, $request, $duration);
        throw $httpException;
    }

    private function dispatchRequestFailed(HttpRequestException $exception, HttpRequest $request, float $duration): void {
        $this->events->dispatch(new HttpRequestFailed([
            'url' => $request->url(),
            'method' => $request->method(),
            'headers' => $request->headers(),
            'body' => $request->body()->toArray(),
            'errors' => $exception->getMessage(),
            'duration' => $duration,
        ]));
    }

    private function validateStatusCodeOrFail(ResponseInterface $response, HttpRequest $request, float $startTime): void {
        if (!$this->config->failOnError || $response->getStatusCode() < 400) {
            return;
        }

        $duration = microtime(true) - $startTime;
        $httpResponse = $this->buildHttpResponse($response, $request);

        $httpException = HttpExceptionFactory::fromStatusCode(
            $response->getStatusCode(),
            $request,
            $httpResponse,
            $duration
        );

        $this->dispatchStatusCodeFailed($response->getStatusCode(), $request, $duration);
        throw $httpException;
    }

    private function dispatchStatusCodeFailed(int $statusCode, HttpRequest $request, float $duration): void {
        $this->events->dispatch(new HttpRequestFailed([
            'url' => $request->url(),
            'method' => $request->method(),
            'statusCode' => $statusCode,
            'duration' => $duration,
        ]));
    }

    private function dispatchResponseReceived(ResponseInterface $response): void {
        $this->events->dispatch(new HttpResponseReceived([
            'statusCode' => $response->getStatusCode()
        ]));
    }

    private function buildHttpResponse(ResponseInterface $response, HttpRequest $request): PsrHttpResponse {
        return new PsrHttpResponse(
            response: $response,
            stream: $response->getBody(),
            events: $this->events,
            isStreamed: $request->isStreamed(),
            streamChunkSize: $this->config->streamChunkSize,
        );
    }
}