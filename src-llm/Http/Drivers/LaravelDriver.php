<?php

namespace Cognesy\LLM\Http\Drivers;

use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\LLM\Http\Adapters\LaravelResponseAdapter;
use Cognesy\LLM\Http\Contracts\CanHandleHttp;
use Cognesy\LLM\Http\Contracts\ResponseAdapter;
use Cognesy\LLM\Http\Data\HttpClientConfig;
use Cognesy\LLM\Http\Data\HttpClientRequest;
use Cognesy\LLM\Http\Events\HttpRequestFailed;
use Cognesy\LLM\Http\Events\HttpRequestSent;
use Cognesy\LLM\Http\Events\HttpResponseReceived;
use Cognesy\LLM\Http\Exceptions\RequestException;
use Cognesy\Utils\Debug\Debug;
use Cognesy\Utils\Result\Result;
use Exception;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
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
            $processedResponses = $this->processBatchResponses($batchResponses);
            foreach ($processedResponses as $response) {
                $responses[] = $response;
            }
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
            if (!$request instanceof HttpClientRequest) {
                throw new InvalidArgumentException('Invalid request type in pool');
            }
            $poolRequests[] = $this->createPoolRequest($pool, $request);
        }
        return $poolRequests;
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
            $responses[] = match(true) {
                $response instanceof Exception => $this->handleException($response),
                $response instanceof Response && $response->failed() => $this->handleFailedResponse($response),
                $response instanceof Response => $this->handleSuccessfulResponse($response),
                default => throw new InvalidArgumentException('Invalid response type in pool'),
            };
        }
        return $responses;
    }

    private function handleSuccessfulResponse(Response $response): Result {
        $this->events->dispatch(new HttpResponseReceived($response->status()));
        return Result::success(new LaravelResponseAdapter($response));
    }

    private function handleFailedResponse(Response $response): Result {
        return match($this->config->failOnError) {
            true => throw new RequestException($response),
            default => Result::failure(new RequestException($response)),
        };
    }

    private function handleException(Exception $response): Result {
        if ($this->config->failOnError) {
            throw $response;
        }
        $this->events->dispatch(new HttpRequestFailed(
            'Pool request',
            'POOL',
            [],
            [],
            $response->getMessage()
        ));
        return Result::failure($response);
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