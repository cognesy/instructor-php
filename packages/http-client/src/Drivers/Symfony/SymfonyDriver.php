<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Symfony;

use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
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
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
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
        if ($clientInstance !== null && !($clientInstance instanceof HttpClientInterface)) {
            throw new \InvalidArgumentException('Client instance of SymfonyDriver must be of type Symfony\Contracts\HttpClient\HttpClientInterface');
        }
        $this->client = $clientInstance ?? SymfonyHttpClient::create(['http_version' => '2.0']);
    }

    #[\Override]
    public function handle(HttpRequest $request) : HttpResponse {
        $this->dispatchRequestSent($request);
        try {
            $rawResponse = $this->performHttpCall($request);
            $httpResponse = $this->buildHttpResponse($rawResponse, $request);
        } catch (TransportExceptionInterface $e) {
            $this->handleTransportException($e, $request);
        } catch (HttpExceptionInterface $e) {
            // Symfony throws HTTP exceptions when accessing status code or content with error codes
            $this->handleHttpException($e, $request);
        }
        if ($this->config->failOnError && $httpResponse->statusCode() >= 400) {
            $httpException = HttpExceptionFactory::fromStatusCode(
                $httpResponse->statusCode(),
                $request,
                $httpResponse,
            );
            $this->dispatchStatusCodeFailed($httpResponse->statusCode(), $request);
            throw $httpException;
        }
        $this->dispatchResponseReceived($httpResponse);
        return $httpResponse;
    }

    // INTERNAL /////////////////////////////////////////////

    private function performHttpCall(HttpRequest $request): ResponseInterface {
        $body = $request->body()->toString();
        $jsonBody = $this->decodeJsonArray($body);
        $serializedBody = match (true) {
            $jsonBody !== null => json_encode($jsonBody) ?: $body,
            default => $body,
        };

        return $this->client->request(
            method: $request->method(),
            url: $request->url(),
            options: [
                'headers' => $request->headers(),
                'body' => $serializedBody,
                'timeout' => $this->config->idleTimeout,
                'max_duration' => $this->config->requestTimeout,
                'buffer' => !$request->isStreamed(),
            ]
        );
    }

    private function buildHttpResponse(ResponseInterface $response, HttpRequest $request): HttpResponse {
        return (new SymfonyHttpResponseAdapter(
            client: $this->client,
            response: $response,
            events: $this->events,
            isStreamed: $request->isStreamed(),
            connectTimeout: $this->config->connectTimeout,
        ))->toHttpResponse();
    }

    // exception handling

    private function handleTransportException(TransportExceptionInterface $e, HttpRequest $request): never {
        $message = $e->getMessage();
        $httpException = match (true) {
            str_contains($message, 'timeout') || str_contains($message, 'timed out')
                => new TimeoutException($message, $request, null, $e),
            str_contains($message, 'Failed to connect') || str_contains($message, 'Could not resolve host')
                => new ConnectionException($message, $request, null, $e),
            default => new NetworkException($message, $request, null, null, $e),
        };
        $this->dispatchRequestFailed($httpException, $request);
        throw $httpException;
    }

    private function handleHttpException(HttpExceptionInterface $e, HttpRequest $request): never {
        $statusCode = $e->getResponse()->getStatusCode();
        $httpException = HttpExceptionFactory::fromStatusCode(
            statusCode: $statusCode,
            request: $request,
            previous: $e
        );
        $this->dispatchStatusCodeFailed($statusCode, $request);
        throw $httpException;
    }

    // event dispatching

    private function dispatchRequestSent(HttpRequest $request): void {
        $this->events->dispatch(new HttpRequestSent([
            'url' => $request->url(),
            'method' => $request->method(),
            'headers' => $request->headers(),
            'body' => $request->body()->toArray(),
        ]));
    }

    private function dispatchStatusCodeFailed(int $statusCode, HttpRequest $request): void {
        $this->events->dispatch(new HttpRequestFailed([
            'url' => $request->url(),
            'method' => $request->method(),
            'statusCode' => $statusCode,
        ]));
    }

    private function dispatchResponseReceived(HttpResponse $response): void {
        $this->events->dispatch(new HttpResponseReceived([
            'statusCode' => $response->statusCode()
        ]));
    }

    private function dispatchRequestFailed(HttpRequestException $exception, HttpRequest $request): void {
        $this->events->dispatch(new HttpRequestFailed([
            'url' => $request->url(),
            'method' => $request->method(),
            'headers' => $request->headers(),
            'body' => $request->body()->toArray(),
            'errors' => $exception->getMessage(),
        ]));
    }

    private function decodeJsonArray(string $body): ?array {
        if ($body === '') {
            return null;
        }

        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }
}
