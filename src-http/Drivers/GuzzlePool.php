<?php

namespace Cognesy\Http\Drivers;

use Cognesy\Http\Adapters\PsrHttpResponse;
use Cognesy\Http\Contracts\CanHandleRequestPool;
use Cognesy\Http\Data\HttpClientConfig;
use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Http\Events\HttpRequestFailed;
use Cognesy\Http\Events\HttpRequestSent;
use Cognesy\Http\Events\HttpResponseReceived;
use Cognesy\Http\Exceptions\RequestException;
use Cognesy\Utils\Events\EventDispatcher;
use Cognesy\Utils\Result\Result;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

class GuzzlePool implements CanHandleRequestPool
{
    public function __construct(
        protected HttpClientConfig $config,
        protected ClientInterface $client,
        protected EventDispatcher $events,
    ) {}

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
            stream: $response->getBody()
        ));
    }

    private function handleRejectedResponse($reason): Result {
        if ($this->config->failOnError) {
            throw new RequestException($reason);
        }

        $this->events->dispatch(new HttpRequestFailed(
            'Pool request',
            'POOL',
            [],
            [],
            $reason->getMessage()
        ));

        return Result::failure($reason);
    }

    private function dispatchRequestEvent(HttpClientRequest $request): void {
        $this->events->dispatch(new HttpRequestSent(
            $request->url(),
            $request->method(),
            $request->headers(),
            $request->body()->toArray()
        ));
    }

    private function normalizeResponses(array $responses): array {
        ksort($responses);
        return array_values($responses);
    }
}