<?php declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\HttpClient;

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
    protected HttpFactory $factory;
    protected ?PendingRequest $basePendingRequest = null;

    public function __construct(
        protected HttpClientConfig $config,
        protected EventDispatcherInterface $events,
        ?object $clientInstance = null,
    ) {
        $this->factory = new HttpFactory();

        match (true) {
            $clientInstance instanceof HttpFactory => $this->factory = $clientInstance,
            $clientInstance instanceof PendingRequest => $this->basePendingRequest = $clientInstance,
            $clientInstance === null => null,
            default => throw new InvalidArgumentException(
                'Client instance must be an instance of Illuminate\Http\Client\Factory or Illuminate\Http\Client\PendingRequest'
            ),
        };
    }

    #[\Override]
    public function handle(HttpRequest $request): HttpResponse
    {
        $this->dispatchRequestSent($request);

        try {
            $response = $this->performHttpCall($request);
        } catch (LaravelConnectionException $e) {
            $this->handleConnectionException($e, $request);
        } catch (\Exception $e) {
            $this->handleNetworkException($e, $request);
        }

        $httpResponse = (new LaravelHttpResponseAdapter(
            response: $response,
            events: $this->events,
            streaming: $request->isStreamed(),
            streamChunkSize: $this->config->streamChunkSize,
        ))->toHttpResponse();

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

    private function performHttpCall(HttpRequest $request): Response
    {
        $pendingRequest = $this->createPendingRequest($request->headers(), $request->isStreamed());
        $method = strtoupper($request->method());
        $body = $request->body()->toString();
        $jsonBody = $this->decodeJsonArray($body);

        if ($method === 'GET') {
            return $pendingRequest->get($request->url());
        }

        if ($jsonBody !== null) {
            return match ($method) {
                'POST' => $pendingRequest->post($request->url(), $jsonBody),
                'PUT' => $pendingRequest->put($request->url(), $jsonBody),
                'PATCH' => $pendingRequest->patch($request->url(), $jsonBody),
                'DELETE' => $pendingRequest->delete($request->url(), $jsonBody),
                default => throw new InvalidArgumentException("Unsupported HTTP method: {$method}"),
            };
        }

        $requestWithBody = $pendingRequest;
        if ($body !== '') {
            $requestWithBody = $requestWithBody->withBody($body, $this->resolveContentType($request->headers()));
        }

        return $requestWithBody->send($method, $request->url());
    }

    private function createPendingRequest(array $headers, bool $streaming): PendingRequest
    {
        if ($this->basePendingRequest !== null) {
            $pendingRequest = clone $this->basePendingRequest;
            $pendingRequest = $pendingRequest
                ->timeout($this->config->requestTimeout)
                ->connectTimeout($this->config->connectTimeout)
                ->withHeaders($headers);

            return match ($streaming) {
                true => $pendingRequest->withOptions(['stream' => true]),
                default => $pendingRequest,
            };
        }

        /** @phpstan-ignore-next-line */
        $pendingRequest = $this->factory
            ->timeout($this->config->requestTimeout)
            ->connectTimeout($this->config->connectTimeout)
            ->withHeaders($headers);

        return match ($streaming) {
            true => $pendingRequest->withOptions(['stream' => true]),
            default => $pendingRequest,
        };
    }

    private function handleConnectionException(LaravelConnectionException $e, HttpRequest $request): never
    {
        $message = $e->getMessage();
        $httpException = str_contains($message, 'timed out') || str_contains($message, 'cURL error 28')
            ? new TimeoutException($message, $request, null, $e)
            : new ConnectionException($message, $request, null, $e);

        $this->dispatchRequestFailed($httpException, $request);
        throw $httpException;
    }

    private function handleNetworkException(\Exception $e, HttpRequest $request): never
    {
        $httpException = new NetworkException($e->getMessage(), $request, null, null, $e);
        $this->dispatchRequestFailed($httpException, $request);
        throw $httpException;
    }

    private function dispatchRequestSent(HttpRequest $request): void
    {
        $this->events->dispatch(new HttpRequestSent([
            'url' => $request->url(),
            'method' => $request->method(),
            'headers' => $request->headers(),
            'body' => $request->body()->toArray(),
        ]));
    }

    private function dispatchStatusCodeFailed(int $statusCode, HttpRequest $request): void
    {
        $this->events->dispatch(new HttpRequestFailed([
            'url' => $request->url(),
            'method' => $request->method(),
            'statusCode' => $statusCode,
        ]));
    }

    private function dispatchRequestFailed(HttpRequestException $exception, HttpRequest $request): void
    {
        $this->events->dispatch(new HttpRequestFailed([
            'url' => $request->url(),
            'method' => $request->method(),
            'headers' => $request->headers(),
            'body' => $request->body()->toArray(),
            'errors' => $exception->getMessage(),
        ]));
    }

    private function dispatchResponseReceived(HttpResponse $response): void
    {
        $this->events->dispatch(new HttpResponseReceived([
            'statusCode' => $response->statusCode(),
        ]));
    }

    private function decodeJsonArray(string $body): ?array
    {
        if ($body === '') {
            return null;
        }

        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return match (true) {
            is_array($decoded) => $decoded,
            default => null,
        };
    }

    private function resolveContentType(array $headers): string
    {
        return match (true) {
            isset($headers['Content-Type']) => (string) $headers['Content-Type'],
            isset($headers['content-type']) => (string) $headers['content-type'],
            default => 'text/plain',
        };
    }
}
