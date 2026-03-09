<?php declare(strict_types=1);

namespace Cognesy\HttpPool\Drivers\Guzzle;

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Collections\HttpRequestList;
use Cognesy\Http\Collections\HttpResponseList;
use Cognesy\HttpPool\Contracts\CanHandleRequestPool;
use Cognesy\HttpPool\Config\HttpPoolConfig;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Events\HttpRequestFailed;
use Cognesy\Http\Events\HttpRequestSent;
use Cognesy\Http\Events\HttpResponseReceived;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Http\Drivers\Guzzle\PsrHttpResponseAdapter;
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
    protected EventDispatcherInterface $events;

    public function __construct(
        protected HttpPoolConfig $config,
        protected ClientInterface $client,
        ?EventDispatcherInterface $events,
    ) {
        $this->events = $events ?? new EventDispatcher();
    }

    /**
     * Handles a pool of HTTP requests concurrently.
     *
     * @param HttpRequestList $requests Collection of HttpRequest objects to be processed.
     * @param int|null $maxConcurrent Maximum number of concurrent requests, defaults to config value.
     * @return HttpResponseList Collection of results for each request, in the same order as the input.
     * @throws HttpRequestException If any request fails and failOnError is true.
     */
    #[\Override]
    public function pool(HttpRequestList $requests, ?int $maxConcurrent = null): HttpResponseList {
        $responses = [];
        $concurrency = $maxConcurrent ?? $this->config->maxConcurrent;

        $requestArray = $requests->all();
        $pool = new Pool($this->client,
            $this->createRequestGenerator($requestArray)(),
            $this->createPoolConfiguration($responses, $requestArray, $concurrency)
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

    private function createPoolConfiguration(array &$responses, array $requests, int $concurrency): array {
        return [
            'concurrency' => $concurrency,
            'fulfilled' => function(ResponseInterface $response, $index) use (&$responses) {
                $responses[$index] = $this->handleFulfilledResponse($response);
            },
            'rejected' => function($reason, $index) use (&$responses, $requests) {
                $responses[$index] = $this->handleRejectedResponse($reason, $requests[$index] ?? null);
            },
        ];
    }

    private function handleFulfilledResponse(ResponseInterface $response): Result {
        $this->events->dispatch(new HttpResponseReceived($response->getStatusCode()));
        $isStreamed = $this->isStreamed($response);
        return Result::success(new PsrHttpResponseAdapter(
            response: $response,
            stream: $response->getBody(),
            events: $this->events,
            isStreamed: $isStreamed,
            streamChunkSize: $this->config->streamChunkSize,
        ));
    }

    private function handleRejectedResponse(mixed $reason, ?HttpRequest $request): Failure {
        $errorMessage = match(true) {
            is_string($reason) => $reason,
            $reason instanceof \Throwable => $reason->getMessage(),
            default => 'Unknown error',
        };

        $this->events->dispatch(new HttpRequestFailed([
            'url' => $request?->url() ?? '',
            'method' => $request?->method() ?? '',
            'errors' => $errorMessage,
        ]));

        if ($this->config->failOnError) {
            throw new HttpRequestException($errorMessage);
        }

        return Result::failure($errorMessage);
    }

    private function dispatchRequestEvent(HttpRequest $request): void {
        $this->events->dispatch(new HttpRequestSent([
            'url' => $request->url(),
            'method' => $request->method(),
            'headers' => $request->headers(),
            'body' => $request->body()->toString()
        ]));
    }

    private function normalizeResponses(array $responses): HttpResponseList {
        ksort($responses);
        return HttpResponseList::fromArray(array_values($responses));
    }

    private function isStreamed(ResponseInterface $response) : bool {
        return $response->getHeaderLine('Content-Type') === 'text/event-stream' ||
            $response->getHeaderLine('Content-Type') === 'application/json-stream' ||
            $response->getHeaderLine('Transfer-Encoding') === 'chunked' ||
            !$response->hasHeader('Content-Length');
    }
}
