<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Laravel;

use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Events\HttpRequestFailed;
use Cognesy\Http\Events\HttpRequestSent;
use Cognesy\Http\Events\HttpResponseReceived;
use Cognesy\Http\Exceptions\HttpExceptionFactory;
use Cognesy\Http\Exceptions\HttpRequestException;
use Exception;
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

    public function handle(HttpRequest $request): HttpResponse {
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

        // Create a pending request with configuration
        $pendingRequest = $this->createPendingRequest($headers, $streaming);

        try {
            // Send the request based on the method
            $response = $this->sendRequest($pendingRequest, $method, $url, $body);
        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            $httpException = HttpExceptionFactory::fromDriverException($e, $request, $duration);
            
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
        if ($this->config->failOnError && $response->status() >= 400) {
            $httpResponse = new LaravelHttpResponse(
                response: $response,
                events: $this->events,
                streaming: $streaming,
                streamChunkSize: $this->config->streamChunkSize,
            );
            
            $httpException = HttpExceptionFactory::fromStatusCode(
                $response->status(),
                $request,
                $httpResponse,
                $duration
            );
            
            $this->events->dispatch(new HttpRequestFailed([
                'url' => $url,
                'method' => $method,
                'statusCode' => $response->status(),
                'duration' => $duration,
            ]));
            
            throw $httpException;
        }

        $this->events->dispatch(new HttpResponseReceived([
            'statusCode' => $response->status()
        ]));
        
        return new LaravelHttpResponse(
            response: $response,
            events: $this->events,
            streaming: $streaming,
            streamChunkSize: $this->config->streamChunkSize,
        );
    }

    // INTERNAL /////////////////////////////////////////////

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
        $pendingRequest = $this->factory
            ->timeout($this->config->requestTimeout)
            ->connectTimeout($this->config->connectTimeout)
            ->withHeaders($headers);
            
        if ($streaming) {
            $pendingRequest->withOptions(['stream' => true]);
        }
        
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