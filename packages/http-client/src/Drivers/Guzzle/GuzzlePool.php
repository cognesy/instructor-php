<?php

namespace Cognesy\Http\Drivers\Guzzle;

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleRequestPool;
use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Http\Events\HttpRequestFailed;
use Cognesy\Http\Events\HttpRequestSent;
use Cognesy\Http\Events\HttpResponseReceived;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Utils\Result\Result;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;

class GuzzlePool implements CanHandleRequestPool
{
    public function __construct(
        protected HttpClientConfig $config,
        protected ClientInterface $client,
        protected ?EventDispatcherInterface $events,
    ) {
        $this->events = $events ?? new EventDispatcher();
    }

    public function pool(array $requests, ?int $maxConcurrent = null): array {
        $responses = [];
        $concurrency = $maxConcurrent ?? $this->config->maxConcurrent;

        $pool = new Pool($this->client,
            $this->createRequestGenerator($requests)(),
            $this->createPoolConfiguration($responses, $concurrency)
        );

        // Execute the pool with a timeout
        $pool->promise()->wait($this->config->poolTimeout);

        return $this->normalizeResponses($responses);
    }

    private function createRequestGenerator(array $requests): callable {
        return function() use ($requests) {
            foreach ($requests as $key => $request) {
                if (!$request instanceof HttpClientRequest) {
                    throw new InvalidArgumentException('Invalid request type in pool');
                }

                $this->dispatchRequestEvent($request);
                yield $key => $this->createPsrRequest($request);
            }
        };
    }

    private function createPsrRequest(HttpClientRequest $request): Request {
        return new Request(
            $request->method(),
            $request->url(),
            $request->headers(),
            $request->body()->toString()
        );
    }

    private function createPoolConfiguration(array &$responses, int $concurrency): array {
        return [
            'concurrency' => $concurrency,
            'fulfilled' => function(ResponseInterface $response, $index) use (&$responses) {
                $responses[$index] = $this->handleFulfilledResponse($response);
            },
            'rejected' => function($reason, $index) use (&$responses) {
                $responses[$index] = $this->handleRejectedResponse($reason);
            },
        ];
    }

    private function handleFulfilledResponse(ResponseInterface $response): Result {
        $this->events->dispatch(new HttpResponseReceived($response->getStatusCode()));
        return Result::success(new PsrHttpResponse(
            response: $response,
            stream: $response->getBody(),
            isStreamed: $response->isStreamed,
        ));
    }

    private function handleRejectedResponse($reason): Result {
        if ($this->config->failOnError) {
            throw new HttpRequestException($reason);
        }

        $this->events->dispatch(new HttpRequestFailed([
            'url' => $request->url(),
            'method' => $request->method(),
            'headers' => $request->headers(),
            'body' => $request->body()->toArray(),
            'errors' => $e->getMessage(),
        ]));

        return Result::failure($reason);
    }

    private function dispatchRequestEvent(HttpClientRequest $request): void {
        $this->events->dispatch(new HttpRequestSent([
            'url' => $url,
            'method' => $method,
            'headers' => $headers,
            'body' => $body,
        ]));
    }

    private function normalizeResponses(array $responses): array {
        ksort($responses);
        return array_values($responses);
    }
}