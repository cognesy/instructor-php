<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Symfony;

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
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpClient\HttpClient as SymfonyHttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SymfonyDriver implements CanHandleHttpRequest
{
    protected HttpClientConfig $config;
    protected EventDispatcherInterface $events;
    protected HttpClientInterface $client;

    public function __construct(
        HttpClientConfig $config,
        EventDispatcherInterface $events,
        ?object $clientInstance = null,
    ) {
        $this->config = $config;
        $this->events = $events;
        if ($clientInstance && !($clientInstance instanceof HttpClientInterface)) {
            throw new \InvalidArgumentException('Client instance of SymfonyDriver must be of type Symfony\Contracts\HttpClient\HttpClientInterface');
        }
        $this->client = $clientInstance ?? SymfonyHttpClient::create(['http_version' => '2.0']);
    }

    public function handle(HttpRequest $request) : HttpResponse {
        $startTime = microtime(true);
        $this->dispatchRequestSent($request);
        try {
            $response = $this->performHttpCall($request);
        } catch (TransportExceptionInterface $e) {
            $this->handleTransportException($e, $request, $startTime);
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

    private function performHttpCall(HttpRequest $request): ResponseInterface {
        return $this->client->request(
            method: $request->method(),
            url: $request->url(),
            options: [
                'headers' => $request->headers(),
                'body' => is_array($request->body()->toArray()) ? json_encode($request->body()->toArray()) : $request->body()->toArray(),
                'timeout' => $this->config->idleTimeout,
                'max_duration' => $this->config->requestTimeout,
                'buffer' => !$request->isStreamed(),
            ]
        );
    }

    private function handleTransportException(TransportExceptionInterface $e, HttpRequest $request, float $startTime): never {
        $duration = microtime(true) - $startTime;
        $message = $e->getMessage();

        $httpException = match (true) {
            str_contains($message, 'timeout') || str_contains($message, 'timed out') 
                => new TimeoutException($message, $request, $duration, $e),
            str_contains($message, 'Failed to connect') || str_contains($message, 'Could not resolve host') 
                => new ConnectionException($message, $request, $duration, $e),
            default => new NetworkException($message, $request, null, $duration, $e),
        };

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

    private function buildHttpResponse(ResponseInterface $response, HttpRequest $request): SymfonyHttpResponse {
        return new SymfonyHttpResponse(
            client: $this->client,
            response: $response,
            events: $this->events,
            isStreamed: $request->isStreamed(),
            connectTimeout: $this->config->connectTimeout,
        );
    }
}
