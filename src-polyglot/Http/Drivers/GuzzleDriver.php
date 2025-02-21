<?php

namespace Cognesy\Polyglot\Http\Drivers;

use Cognesy\Polyglot\Http\Adapters\PsrResponseAdapter;
use Cognesy\Polyglot\Http\Contracts\CanHandleHttp;
use Cognesy\Polyglot\Http\Contracts\ResponseAdapter;
use Cognesy\Polyglot\Http\Data\HttpClientConfig;
use Cognesy\Polyglot\Http\Data\HttpClientRequest;
use Cognesy\Polyglot\Http\Events\HttpRequestFailed;
use Cognesy\Polyglot\Http\Events\HttpRequestSent;
use Cognesy\Polyglot\Http\Events\HttpResponseReceived;
use Cognesy\Polyglot\Http\Exceptions\RequestException;
use Cognesy\Utils\Debug\Debug;
use Cognesy\Utils\Events\EventDispatcher;
use Cognesy\Utils\Result\Result;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Pool;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Psr7\CachingStream;
use GuzzleHttp\Psr7\Request;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class GuzzleDriver implements CanHandleHttp
{
    protected Client $client;

    public function __construct(
        protected HttpClientConfig $config,
        protected ?Client $httpClient = null,
        protected ?EventDispatcher $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();

        // First check if debugging is enabled with a custom client
        if (Debug::isEnabled() && isset($this->httpClient)) {
            throw new InvalidArgumentException("Guzzle does not allow to inject debugging stack into existing client. Turn off debug or use default client.");
        }

        // Handle client initialization based on debug mode and custom client
        $this->client = match(true) {
            // When debugging is enabled, always create new client with debug stack
            Debug::isEnabled() => new Client(['handler' => $this->addDebugStack(HandlerStack::create())]),
            // When custom client is provided and debug is off, use it
            isset($this->httpClient) => $this->httpClient,
            // Default case: create new client without debug stack
            default => new Client()
        };
    }

    public function handle(HttpClientRequest $request) : ResponseAdapter {
        $url = $request->url();
        $headers = $request->headers();
        $body = $request->body();
        $method = $request->method();
        $streaming = $request->isStreamed();
        $this->events->dispatch(new HttpRequestSent($url, $method, $headers, $body));
        Debug::tryDumpUrl($url);
        try {
            $response = $this->client->request($method, $url, [
                'headers' => $headers,
                'json' => $body,
                'connect_timeout' => $this->config->connectTimeout ?? 3,
                'timeout' => $this->config->requestTimeout ?? 30,
                'debug' => Debug::isFlag('http.trace') ?? false,
                'stream' => $streaming,
            ]);
        } catch (Exception $e) {
            $this->events->dispatch(new HttpRequestFailed($url, $method, $headers, $body, $e->getMessage()));
            throw new RequestException($e);
        }
        $this->events->dispatch(new HttpResponseReceived($response->getStatusCode()));
        return new PsrResponseAdapter(
            response: $response,
            stream: $response->getBody()
        );
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
            json_encode($request->body())
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
        return Result::success(new PsrResponseAdapter(
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
            $request->body()
        ));
    }

    private function normalizeResponses(array $responses): array {
        ksort($responses);
        return array_values($responses);
    }

    // INTERNAL /////////////////////////////////////////////////

    protected function addDebugStack(HandlerStack $stack) : HandlerStack {
        // add caching stream to make response body rewindable
        $stack->push(Middleware::mapResponse(function (ResponseInterface $response) {
            return $response->withBody(new CachingStream($response->getBody()));
        }));

        $stack->push(Middleware::tap(
            function (RequestInterface $request, $options) {
                Debug::tryDumpRequest($request);
                Debug::tryDumpTrace();
            },
            function ($request, $options, FulfilledPromise|RejectedPromise $response) {
                $response->then(function (ResponseInterface $response) use ($request, $options) {
                    Debug::tryDumpResponse($response, $options);
                    // need to rewind body to read it again in main flow
                    $response->getBody()->rewind();
                });
            })
        );
        return $stack;
    }
}