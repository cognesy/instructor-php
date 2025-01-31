<?php

namespace Cognesy\Instructor\Features\Http\Drivers;

use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\HttpClient\HttpRequestSent;
use Cognesy\Instructor\Events\HttpClient\HttpRequestFailed;
use Cognesy\Instructor\Events\HttpClient\HttpResponseReceived;
use Cognesy\Instructor\Features\Http\Adapters\LaravelResponseAdapter;
use Cognesy\Instructor\Features\Http\Contracts\ResponseAdapter;
use Cognesy\Instructor\Features\Http\Contracts\CanHandleHttp;
use Cognesy\Instructor\Features\Http\Data\HttpClientConfig;
use Cognesy\Instructor\Features\Http\Data\HttpClientRequest;
use Cognesy\Instructor\Features\Http\Exceptions\RequestException;
use Cognesy\Instructor\Utils\Debug\Debug;
use Cognesy\Instructor\Utils\Result\Result;
use Exception;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Client\PendingRequest;
use InvalidArgumentException;

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

    public function handle(HttpClientRequest $request): ResponseAdapter {
        $url = $request->url();
        $headers = $request->headers();
        $body = $request->body();
        $method = $request->method();
        $streaming = $request->isStreamed();

        $this->events->dispatch(new HttpRequestSent($url, $method, $headers, $body));
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
            $this->events->dispatch(new HttpRequestFailed($url, $method, $headers, $body, $e->getMessage()));
            throw new RequestException($e);
        }
        $this->events->dispatch(new HttpResponseReceived($response->status()));
        return new LaravelResponseAdapter($response, $streaming);
    }

    public function pool(array $requests, ?int $maxConcurrent = 5): array {
        $responses = [];
        $batches = array_chunk($requests, $maxConcurrent);

        foreach ($batches as $batch) {
            $batchResponses = $this->processBatch($batch);
            $responses = array_merge($responses, $this->processBatchResponses($batchResponses));
        }

        return $responses;
    }

    // INTERNAL /////////////////////////////////////////////

    private function processBatch(array $batch): array {
        return $this->factory->pool(function (Pool $pool) use ($batch) {
            return $this->createPoolRequests($pool, $batch);
        });
    }

    private function createPoolRequests(Pool $pool, array $batch): array {
        $poolRequests = [];

        foreach ($batch as $request) {
            $this->validateRequest($request);
            $poolRequests[] = $this->createPoolRequest($pool, $request);
        }

        return $poolRequests;
    }

    private function validateRequest($request): void {
        if (!$request instanceof HttpClientRequest) {
            throw new InvalidArgumentException('Invalid request type in pool');
        }
    }

    private function createPoolRequest(Pool $pool, HttpClientRequest $request) {
        return $pool->withOptions([
            'timeout' => $this->config->requestTimeout,
            'connect_timeout' => $this->config->connectTimeout,
            'headers' => $request->headers(),
        ])->{strtolower($request->method())}(
            $request->url(),
            $request->method() === 'GET' ? [] : $request->body(),
        );
    }

    private function processBatchResponses(array $batchResponses): array {
        $responses = [];

        foreach ($batchResponses as $response) {
            $responses[] = $this->processResponse($response);
        }

        return $responses;
    }

    private function processResponse($response): Result {
        if (!$response instanceof Response) {
            return $this->handleNonResponse($response);
        }

        if ($response->failed()) {
            return $this->handleFailedResponse($response);
        }

        return $this->handleSuccessfulResponse($response);
    }

    private function handleSuccessfulResponse(Response $response): Result {
        $this->events->dispatch(new HttpResponseReceived($response->status()));
        return Result::success(new LaravelResponseAdapter($response));
    }

    private function handleFailedResponse(Response $response): Result {
        if ($this->config->failOnError) {
            throw new RequestException($response);
        }
        return Result::failure(new RequestException($response));
    }

    private function handleNonResponse($response): Result {
        if ($this->config->failOnError) {
            throw new RequestException($response);
        }

        $this->events->dispatch(new HttpRequestFailed(
            'Pool request',
            'POOL',
            [],
            [],
            $response->getMessage()
        ));

        return Result::failure(new RequestException($response));
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