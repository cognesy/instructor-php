<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Guzzle;

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
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException as GuzzleConnectException;
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
        if ($clientInstance !== null && !($clientInstance instanceof ClientInterface)) {
            throw new \InvalidArgumentException('Client instance of GuzzleDriver must be of type GuzzleHttp\ClientInterface');
        }
        $this->client = $clientInstance ?? new Client();
    }

    #[\Override]
    public function handle(HttpRequest $request) : HttpResponse {
        $this->dispatchRequestSent($request);
        try {
            $rawResponse = $this->performHttpCall($request);
        } catch (GuzzleConnectException $e) {
            $this->handleConnectionException($e, $request);
        } catch (Exception $e) {
            $this->handleNetworkException($e, $request);
        }

        $httpResponse = $this->buildHttpResponse($rawResponse, $request);
        if ($this->config->failOnError && $httpResponse->statusCode() >= 400) {
            $httpException = HttpExceptionFactory::fromStatusCode($httpResponse->statusCode(), $request, $httpResponse);
            $this->dispatchStatusCodeFailed($httpResponse->statusCode(), $request);
            throw $httpException;
        }
        $this->dispatchResponseReceived($httpResponse);
        return $httpResponse;
    }

    // INTERNAL //////////////////////////////////////////////////////////////////////////

    private function performHttpCall(HttpRequest $request) : ResponseInterface {
        $body = $request->body()->toString();
        $jsonBody = $this->decodeJsonArray($body);
        $options = [
            'headers' => $request->headers(),
            'connect_timeout' => $this->config->connectTimeout ?? 3,
            'timeout' => $this->config->requestTimeout ?? 30,
            'stream' => $request->isStreamed(),
            'http_errors' => false, // Disable Guzzle's automatic HTTP error handling
        ];

        if ($jsonBody !== null) {
            $options['json'] = $jsonBody;
        }

        if ($jsonBody === null && $body !== '') {
            $options['body'] = $body;
        }

        return $this->client->request($request->method(), $request->url(), $options);
    }

    private function buildHttpResponse(ResponseInterface $response, HttpRequest $request): HttpResponse {
        return (new PsrHttpResponseAdapter(
            response: $response,
            stream: $response->getBody(),
            events: $this->events,
            isStreamed: $request->isStreamed(),
            streamChunkSize: $this->config->streamChunkSize,
        ))->toHttpResponse();
    }

    // exception handling

    private function handleConnectionException(GuzzleConnectException $e, HttpRequest $request): never {
        $message = $e->getMessage();

        $httpException = str_contains($message, 'timeout') || str_contains($message, 'timed out')
            ? new TimeoutException($message, $request, null, $e)
            : new ConnectionException($message, $request, null, $e);

        $this->dispatchRequestFailed($httpException, $request);
        throw $httpException;
    }

    private function handleNetworkException(Exception $e, HttpRequest $request): never {
        $httpException = new NetworkException($e->getMessage(), $request, null, null, $e);
        $this->dispatchRequestFailed($httpException, $request);
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

    private function dispatchRequestFailed(HttpRequestException $exception, HttpRequest $request): void {
        $this->events->dispatch(new HttpRequestFailed([
            'url' => $request->url(),
            'method' => $request->method(),
            'headers' => $request->headers(),
            'body' => $request->body()->toArray(),
            'errors' => $exception->getMessage(),
        ]));
    }

    private function dispatchResponseReceived(HttpResponse $response): void {
        $this->events->dispatch(new HttpResponseReceived([
            'statusCode' => $response->statusCode()
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
