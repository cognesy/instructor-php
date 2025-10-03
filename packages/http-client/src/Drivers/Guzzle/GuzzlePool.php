<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Guzzle;

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleRequestPool;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Events\HttpRequestSent;
use Cognesy\Http\Events\HttpResponseReceived;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Utils\Result\Failure;
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

    /**
     * Handles a pool of HTTP requests concurrently.
     *
     * @param HttpRequest[] $requests Array of HttpRequest objects to be processed.
     * @param int|null $maxConcurrent Maximum number of concurrent requests, defaults to config value.
     * @return array Array of results for each request, in the same order as the input.
     * @throws HttpRequestException If any request fails and failOnError is true.
     */
    #[\Override]
    public function pool(array $requests, ?int $maxConcurrent = null): array {
        $responses = [];
        $concurrency = $maxConcurrent ?? $this->config->maxConcurrent;

        $pool = new Pool($this->client,
            $this->createRequestGenerator($requests)(),
            $this->createPoolConfiguration($responses, $concurrency)
        );

        // Execute the pool with a timeout
        $promise = $pool->promise();
        $promise->wait(unwrap: true);

        return $this->normalizeResponses($responses);
    }

    // INTERNAL ////////////////////////////////////////////////////////////////

    /**
     * @return callable(): \Generator
     */
    private function createRequestGenerator(array $requests): callable {
        return function() use ($requests) {
            foreach ($requests as $key => $request) {
                if (!$request instanceof HttpRequest) {
                    throw new InvalidArgumentException('Invalid request type in pool');
                }

                $this->dispatchRequestEvent($request);
                yield $key => $this->createPsrRequest($request);
            }
        };
    }

    private function createPsrRequest(HttpRequest $request): Request {
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
        if ($this->events !== null) {
            $this->events->dispatch(new HttpResponseReceived($response->getStatusCode()));
        }
        $isStreamed = $this->isStreamed($response);
        if ($this->events === null) {
            throw new \RuntimeException('Event dispatcher is required for pooled requests');
        }
        return Result::success(new PsrHttpResponse(
            response: $response,
            stream: $response->getBody(),
            events: $this->events,
            isStreamed: $isStreamed,
            streamChunkSize: $this->config->streamChunkSize,
        ));
    }

    /**
     * @param mixed $reason
     */
    private function handleRejectedResponse($reason): Failure {
        if ($this->config->failOnError) {
            $errorMessage = is_string($reason) ? $reason : 'Unknown error';
            throw new HttpRequestException($errorMessage);
        }
        // TODO: we don't know how to handle this atm
        //        $this->events->dispatch(new HttpRequestFailed([
        //            'url' => $request->url(),
        //            'method' => $request->method(),
        //            'headers' => $request->headers(),
        //            'body' => $request->body()->toArray(),
        //            'errors' => $e->getMessage(),
        //        ]));
        return Result::failure($reason);
    }

    private function dispatchRequestEvent(HttpRequest $request): void {
        if ($this->events !== null) {
            $this->events->dispatch(new HttpRequestSent([
                'url' => $request->url(),
                'method' => $request->method(),
                'headers' => $request->headers(),
                'body' => $request->body()->toString()
            ]));
        }
    }

    private function normalizeResponses(array $responses): array {
        ksort($responses);
        return array_values($responses);
    }

    private function isStreamed(ResponseInterface $response) : bool {
        return $response->getHeaderLine('Content-Type') === 'text/event-stream' ||
            $response->getHeaderLine('Content-Type') === 'application/json-stream' ||
            $response->getHeaderLine('Transfer-Encoding') === 'chunked' ||
            !$response->hasHeader('Content-Length');
    }
}