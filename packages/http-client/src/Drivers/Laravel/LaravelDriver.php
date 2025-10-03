<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Laravel;

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
        
        match (true) {
            $clientInstance instanceof HttpFactory => $this->factory = $clientInstance,
            $clientInstance instanceof PendingRequest => $this->setupFromPendingRequest($clientInstance),
            $clientInstance === null => $this->factory = new HttpFactory(),
            default => throw new \InvalidArgumentException(
                'Client instance must be an instance of Illuminate\Http\Client\Factory or Illuminate\Http\Client\PendingRequest'
            )
        };
    }

    #[\Override]
    public function handle(HttpRequest $request): HttpResponse {
        $startTime = microtime(true);
        $this->dispatchRequestSent($request);
        try {
            $response = $this->performHttpCall($request);
        } catch (LaravelConnectionException $e) {
            $this->handleConnectionException($e, $request, $startTime);
        } catch (\Exception $e) {
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

    private function performHttpCall(HttpRequest $request): Response {
        $pendingRequest = $this->createPendingRequest($request->headers(), $request->isStreamed());
        return $this->sendRequest($pendingRequest, $request->method(), $request->url(), $request->body()->toArray());
    }

    private function handleConnectionException(LaravelConnectionException $e, HttpRequest $request, float $startTime): never {
        $duration = microtime(true) - $startTime;
        $message = $e->getMessage();

        $httpException = str_contains($message, 'timed out') || str_contains($message, 'cURL error 28')
            ? new TimeoutException($message, $request, $duration, $e)
            : new ConnectionException($message, $request, $duration, $e);

        $this->dispatchRequestFailed($httpException, $request, $duration);
        throw $httpException;
    }

    private function handleNetworkException(\Exception $e, HttpRequest $request, float $startTime): never {
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

    private function validateStatusCodeOrFail(Response $response, HttpRequest $request, float $startTime): void {
        if (!$this->config->failOnError || $response->status() < 400) {
            return;
        }

        $duration = microtime(true) - $startTime;
        $httpResponse = $this->buildHttpResponse($response, $request);

        $httpException = HttpExceptionFactory::fromStatusCode(
            $response->status(),
            $request,
            $httpResponse,
            $duration
        );

        $this->dispatchStatusCodeFailed($response->status(), $request, $duration);
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

    private function dispatchResponseReceived(Response $response): void {
        $this->events->dispatch(new HttpResponseReceived([
            'statusCode' => $response->status()
        ]));
    }

    private function buildHttpResponse(Response $response, HttpRequest $request): LaravelHttpResponse {
        return new LaravelHttpResponse(
            response: $response,
            events: $this->events,
            streaming: $request->isStreamed(),
            streamChunkSize: $this->config->streamChunkSize,
        );
    }

    private function setupFromPendingRequest(PendingRequest $pendingRequest): void {
        // Extract factory using reflection (protected property)
        $reflection = new \ReflectionClass($pendingRequest);
        $factoryProperty = $reflection->getProperty('factory');
        $factoryProperty->setAccessible(true);
        $this->factory = $factoryProperty->getValue($pendingRequest);
        
        // Store base configured PendingRequest for cloning
        $this->basePendingRequest = $pendingRequest;
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
}