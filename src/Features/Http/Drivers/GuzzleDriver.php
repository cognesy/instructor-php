<?php

namespace Cognesy\Instructor\Features\Http\Drivers;

use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\HttpClient\RequestSentToLLM;
use Cognesy\Instructor\Events\HttpClient\RequestToLLMFailed;
use Cognesy\Instructor\Events\HttpClient\ResponseReceivedFromLLM;
use Cognesy\Instructor\Features\Http\Adapters\PsrResponse;
use Cognesy\Instructor\Features\Http\Contracts\CanAccessResponse;
use Cognesy\Instructor\Features\Http\Contracts\CanHandleHttp;
use Cognesy\Instructor\Features\Http\Data\HttpClientConfig;
use Cognesy\Instructor\Features\Http\Data\HttpClientRequest;
use Cognesy\Instructor\Utils\Debug\Debug;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Pool;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Psr7\CachingStream;
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

    public function handle(HttpClientRequest $request) : CanAccessResponse {
        $url = $request->url();
        $headers = $request->headers();
        $body = $request->body();
        $method = $request->method();
        $streaming = $request->isStreamed();

        $this->events->dispatch(new RequestSentToLLM($url, $method, $headers, $body));
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
            $this->events->dispatch(new RequestToLLMFailed($url, $method, $headers, $body, $e->getMessage()));
            throw $e;
        }
        $this->events->dispatch(new ResponseReceivedFromLLM($response->getStatusCode()));
        return new PsrResponse(
            response: $response,
            stream: $response->getBody()
        );
    }

    public function pool(array $requests, ?int $maxConcurrent = null): array {
        $promises = [];
        $responses = [];

        // Use class config if maxConcurrent not provided
        $concurrency = $maxConcurrent ?? $this->config->maxConcurrent;

        // Create promises for each request
        foreach ($requests as $key => $request) {
            if (!$request instanceof HttpClientRequest) {
                throw new InvalidArgumentException('Invalid request type in pool');
            }

            $promises[$key] = $this->client->requestAsync(
                $request->method(),
                $request->url(),
                [
                    'headers' => $request->headers(),
                    'json' => $request->body(),
                    'connect_timeout' => $this->config->connectTimeout,
                    'timeout' => $this->config->requestTimeout,
                    'debug' => Debug::isFlag('http.trace') ?? false,
                ]
            );
        }

        // Use Guzzle's Pool for handling concurrent requests
        $pool = new Pool($this->client, $promises, [
            'concurrency' => $concurrency,
            'fulfilled' => function ($response, $index) use (&$responses) {
                $responses[$index] = new PsrResponse(
                    response: $response,
                    stream: $response->getBody()
                );
            },
            'rejected' => function ($reason, $index) {
                if ($this->config->failOnError) {
                    throw $reason;
                }
                // Log or handle the error as needed
                $this->events->dispatch(new RequestToLLMFailed(
                    'Pool request ' . $index,
                    'POOL',
                    [],
                    [],
                    $reason->getMessage()
                ));
            },
        ]);

        // Execute the pool with a timeout
        $promise = $pool->promise();
        $promise->wait($this->config->poolTimeout);

        // Ensure responses are in the same order as requests
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