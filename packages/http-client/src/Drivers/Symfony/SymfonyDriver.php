<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Symfony;

use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Events\HttpRequestFailed;
use Cognesy\Http\Events\HttpRequestSent;
use Cognesy\Http\Drivers\DispatchesHttpDriverEvents;
use Cognesy\Http\Exceptions\ConnectionException;
use Cognesy\Http\Exceptions\HttpExceptionFactory;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Http\Exceptions\NetworkException;
use Cognesy\Http\Exceptions\TimeoutException;
use Cognesy\Http\Telemetry\HttpRequestTelemetry;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpClient\HttpClient as SymfonyHttpClient;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TimeoutExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SymfonyDriver implements CanHandleHttpRequest
{
    use DispatchesHttpDriverEvents;

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
        $this->client = $clientInstance ?? SymfonyHttpClient::create([
            'http_version' => $this->configuredHttpVersion(),
            'verify_peer' => $this->config->verifyTls,
            'verify_host' => $this->config->verifyTls,
            'max_redirects' => $this->configuredMaxRedirects(),
        ]);
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
        $this->dispatchResponseReceived($request, $httpResponse->statusCode(), $httpResponse->isStreamed(), $httpResponse->isStreamed() ? null : $httpResponse->body());
        return $httpResponse;
    }

    // INTERNAL /////////////////////////////////////////////

    private function performHttpCall(HttpRequest $request): ResponseInterface {
        $body = $request->body()->toString();

        return $this->client->request(
            method: $request->method(),
            url: $request->url(),
            options: [
                'headers' => $request->headers(),
                'body' => $body,
                'timeout' => $this->config->idleTimeout,
                'max_duration' => $this->config->requestTimeout,
                'buffer' => !$request->isStreamed(),
                'verify_peer' => $this->config->verifyTls,
                'verify_host' => $this->config->verifyTls,
                'max_redirects' => $this->configuredMaxRedirects(),
                'http_version' => $this->configuredHttpVersion(),
            ]
        );
    }

    private function configuredMaxRedirects(): int {
        return match ($this->config->followRedirects) {
            true => $this->config->maxRedirects ?? 20,
            false => 0,
        };
    }

    private function configuredHttpVersion(): string {
        return $this->config->httpVersion ?? '2.0';
    }

    private function buildHttpResponse(ResponseInterface $response, HttpRequest $request): HttpResponse {
        return (new SymfonyHttpResponseAdapter(
            client: $this->client,
            response: $response,
            events: $this->events,
            isStreamed: $request->isStreamed(),
            requestId: $request->id,
            connectTimeout: $this->config->connectTimeout,
        ))->toHttpResponse();
    }

    // exception handling

    private function handleTransportException(TransportExceptionInterface $e, HttpRequest $request): never {
        $message = $e->getMessage();
        $httpException = match (true) {
            $e instanceof TimeoutExceptionInterface
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





}
