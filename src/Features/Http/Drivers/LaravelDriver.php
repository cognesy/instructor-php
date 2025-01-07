<?php

namespace Cognesy\Instructor\Features\Http\Drivers;

use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\HttpClient\RequestSentToLLM;
use Cognesy\Instructor\Events\HttpClient\RequestToLLMFailed;
use Cognesy\Instructor\Events\HttpClient\ResponseReceivedFromLLM;
use Cognesy\Instructor\Features\Http\Adapters\LaravelResponse;
use Cognesy\Instructor\Features\Http\Contracts\CanAccessResponse;
use Cognesy\Instructor\Features\Http\Contracts\CanHandleHttp;
use Cognesy\Instructor\Features\Http\Data\HttpClientConfig;
use Cognesy\Instructor\Features\Http\Data\HttpClientRequest;
use Cognesy\Instructor\Utils\Debug\Debug;
use Exception;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;

class LaravelDriver implements CanHandleHttp
{
    private HttpFactory $factory;

    public function __construct(
        protected HttpClientConfig $config,
        protected ?HttpFactory     $httpClient = null,
        protected ?EventDispatcher $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->factory = $httpClient ?? new HttpFactory();
    }

    public function handle(HttpClientRequest $request): CanAccessResponse {
        $url = $request->url();
        $headers = $request->headers();
        $body = $request->body();
        $method = $request->method();
        $streaming = $request->isStreamed();

        $this->events->dispatch(new RequestSentToLLM($url, $method, $headers, $body));
        Debug::tryDumpUrl($url);

        // Create a fresh pending request with configuration
        $pendingRequest = $this->factory
            ->timeout($this->config->requestTimeout)
            ->connectTimeout($this->config->connectTimeout)
            ->withHeaders($headers);

        if ($streaming) {
            $pendingRequest->withOptions(['stream' => true]);
        }

        try {
            // Send the request based on the method
            $response = $this->sendRequest($pendingRequest, $method, $url, $body);
        } catch (Exception $e) {
            $this->events->dispatch(new RequestToLLMFailed($url, $method, $headers, $body, $e->getMessage()));
            throw $e;
        }
        $this->events->dispatch(new ResponseReceivedFromLLM($response->status()));
        return new LaravelResponse($response, $streaming);
    }

    public function pool(array $requests, ?int $maxConcurrent = 5): array {
        $responses = $this->factory->pool(
            fn(Pool $pool) => $this->buildPoolRequests($pool, $requests, $maxConcurrent),
        );

        // Convert Laravel responses to our response type
        return array_map(
            fn(Response $response) => new LaravelResponse($response),
            $responses,
        );
    }

    // INTERNAL /////////////////////////////////////////////////

    private function buildPoolRequests(Pool $pool, array $requests, int $maxConcurrent): array {
        $pool->concurrency($maxConcurrent);
        $poolRequests = [];

        foreach ($requests as $request) {
            if (!$request instanceof HttpClientRequest) {
                throw new Exception('Invalid request type in pool');
            }
            $poolRequests[] = $pool->withOptions([
                'timeout' => $this->config->requestTimeout,
                'connect_timeout' => $this->config->connectTimeout,
                'headers' => $request->headers(),
            ])->{strtolower($request->method())}(
                $request->url(),
                $request->method() === 'GET' ? [] : $request->body(),
            );
        }

        return $poolRequests;
    }

    private function sendRequest(PendingRequest $pendingRequest, string $method, string $url, array $body): Response {
        return match (strtoupper($method)) {
            'GET' => $pendingRequest->get($url),
            'POST' => $pendingRequest->post($url, $body),
            'PUT' => $pendingRequest->put($url, $body),
            'PATCH' => $pendingRequest->patch($url, $body),
            'DELETE' => $pendingRequest->delete($url, $body),
            default => throw new Exception("Unsupported HTTP method: {$method}")
        };
    }
}