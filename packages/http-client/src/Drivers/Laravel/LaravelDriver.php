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
        return $this->sendRequest($pendingRequest, $request->method(), $request->url(), $request->body()->toArray());
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

    private function sendRequest(PendingRequest $pendingRequest, string $method, string $url, array $body): Response {
        return match (strtoupper($method)) {
            'GET' => $pendingRequest->get($url),
            'POST' => $pendingRequest->post($url, $body),
            'PUT' => $pendingRequest->put($url, $body),
            'PATCH' => $pendingRequest->patch($url, $body),
            'DELETE' => $pendingRequest->delete($url, $body),
            default => throw new InvalidArgumentException("Unsupported HTTP method: {$method}")
        };
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
}
