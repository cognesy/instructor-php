<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Laravel;

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
use Illuminate\Http\Client\ConnectionException as LaravelConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;

class LaravelDriver implements CanHandleHttpRequest
{
    protected HttpClientConfig $config;
    protected EventDispatcherInterface $events;
    protected HttpFactory $factory;
    protected ?PendingRequest $basePendingRequest = null;

    public function __construct(
        HttpClientConfig $config,
        EventDispatcherInterface $events,
        ?object $clientInstance = null,
    ) {
        $this->config = $config;
        $this->events = $events;
        // Always initialize a safe default factory
        $this->factory = new HttpFactory();

        match (true) {
            $clientInstance instanceof HttpFactory => $this->factory = $clientInstance,
            $clientInstance instanceof PendingRequest => $this->basePendingRequest = $clientInstance,
            $clientInstance === null => null,
            default => throw new \InvalidArgumentException(
                'Client instance must be an instance of Illuminate\Http\Client\Factory or Illuminate\Http\Client\PendingRequest'
            )
        };
    }

    #[\Override]
    public function handle(HttpRequest $request): HttpResponse {
        $this->dispatchRequestSent($request);

        try {
            $response = $this->performHttpCall($request);
        } catch (LaravelConnectionException $e) {
            $this->handleConnectionException($e, $request);
        } catch (\Exception $e) {
            $this->handleNetworkException($e, $request);
        }

        $httpResponse = $this->buildHttpResponse($response, $request);

        if ($this->config->failOnError && $response->status() >= 400) {
            $httpException = HttpExceptionFactory::fromStatusCode(
                $response->status(),
                $request,
                $httpResponse,
            );
            $this->dispatchStatusCodeFailed($response->status(), $request);
            throw $httpException;
        }

        $this->dispatchResponseReceived($httpResponse);
        return $httpResponse;
    }

    // INTERNAL //////////////////////////////////////////////////////////////////////////

    private function performHttpCall(HttpRequest $request): Response {
        $pendingRequest = $this->createPendingRequest($request->headers(), $request->isStreamed());
        $method = strtoupper($request->method());
        $body = $request->body()->toString();
        $jsonBody = $this->decodeJsonArray($body);

        if ($method === 'GET') {
            return $pendingRequest->get($request->url());
        }

        if ($jsonBody !== null) {
            return $this->sendJsonRequest($pendingRequest, $method, $request->url(), $jsonBody);
        }

        return $this->sendRawRequest($pendingRequest, $method, $request->url(), $body, $request->headers());
    }

    private function buildHttpResponse(Response $response, HttpRequest $request): HttpResponse {
        return (new LaravelHttpResponseAdapter(
            response: $response,
            events: $this->events,
            streaming: $request->isStreamed(),
            streamChunkSize: $this->config->streamChunkSize,
        ))->toHttpResponse();
    }

    private function createPendingRequest(array $headers, bool $streaming): PendingRequest {
        if ($this->basePendingRequest) {
            // Clone pre-configured PendingRequest and apply our config
            $pendingRequest = clone $this->basePendingRequest;
            $pendingRequest = $pendingRequest
                ->timeout($this->config->requestTimeout)
                ->connectTimeout($this->config->connectTimeout)
                ->withHeaders($headers);
                
            if ($streaming) {
                $pendingRequest = $pendingRequest->withOptions(['stream' => true]);
            }
            return $pendingRequest;
        }
        
        // Fallback to Factory-based creation (existing logic)
        /** @phpstan-ignore-next-line */
        $pendingRequest = $this->factory
            ->timeout($this->config->requestTimeout)
            ->connectTimeout($this->config->connectTimeout)
            ->withHeaders($headers);

        if ($streaming) {
            $pendingRequest = $pendingRequest->withOptions(['stream' => true]);
        }

        /** @var PendingRequest $pendingRequest */
        return $pendingRequest;
    }

    private function sendJsonRequest(PendingRequest $pendingRequest, string $method, string $url, array $body): Response {
        return match (strtoupper($method)) {
            'POST' => $pendingRequest->post($url, $body),
            'PUT' => $pendingRequest->put($url, $body),
            'PATCH' => $pendingRequest->patch($url, $body),
            'DELETE' => $pendingRequest->delete($url, $body),
            default => throw new InvalidArgumentException("Unsupported HTTP method: {$method}")
        };
    }

    private function sendRawRequest(
        PendingRequest $pendingRequest,
        string $method,
        string $url,
        string $body,
        array $headers,
    ): Response {
        $requestWithBody = $pendingRequest;
        if ($body !== '') {
            $requestWithBody = $requestWithBody->withBody($body, $this->resolveContentType($headers));
        }

        return $requestWithBody->send($method, $url);
    }

    // exception handling

    private function handleConnectionException(LaravelConnectionException $e, HttpRequest $request): never {
        $message = $e->getMessage();

        $httpException = str_contains($message, 'timed out') || str_contains($message, 'cURL error 28')
            ? new TimeoutException($message, $request, null, $e)
            : new ConnectionException($message, $request, null, $e);

        $this->dispatchRequestFailed($httpException, $request);
        throw $httpException;
    }

    private function handleNetworkException(\Exception $e, HttpRequest $request): never {
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

    private function resolveContentType(array $headers): string {
        return match (true) {
            isset($headers['Content-Type']) => (string) $headers['Content-Type'],
            isset($headers['content-type']) => (string) $headers['content-type'],
            default => 'text/plain',
        };
    }
}
