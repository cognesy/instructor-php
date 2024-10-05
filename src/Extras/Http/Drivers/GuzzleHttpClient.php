<?php

namespace Cognesy\Instructor\Extras\Http\Drivers;

use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\HttpClient\RequestSentToLLM;
use Cognesy\Instructor\Events\HttpClient\RequestToLLMFailed;
use Cognesy\Instructor\Events\HttpClient\ResponseReceivedFromLLM;
use Cognesy\Instructor\Extras\Debug\Debug;
use Cognesy\Instructor\Extras\Http\Contracts\CanHandleHttp;
use Cognesy\Instructor\Extras\Http\Data\HttpClientConfig;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Psr7\CachingStream;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class GuzzleHttpClient implements CanHandleHttp
{
    protected Client $client;

    public function __construct(
        protected HttpClientConfig $config,
        protected ?Client $httpClient = null,
        protected ?EventDispatcher $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        if (isset($this->httpClient) && Debug::isEnabled()) {
            throw new InvalidArgumentException("Guzzle does not allow to inject debugging stack into existing client. Turn off debug or use default client.");
        }
//        $this->client = match(Debug::isEnabled()) {
//            false => $httpClient ?? new Client(),
//            true => new Client(['handler' => $this->addDebugStack(HandlerStack::create())]),
//        };
        $this->client = new Client(['handler' => $this->addDebugStack(HandlerStack::create())]);
    }

    public function handle(
        string $url,
        array $headers,
        array $body,
        string $method = 'POST',
        bool $streaming = false
    ) : ResponseInterface {
        $this->events->dispatch(new RequestSentToLLM($url, $method, $headers, $body));
        try {
            $response = $this->client->request($method, $url, [
                'headers' => $headers,
                'json' => $body,
                'connect_timeout' => $this->config->connectTimeout ?? 3,
                'timeout' => $this->config->requestTimeout ?? 30,
                'debug' => Debug::isFlag('http.trace') ?? false,
                'stream' => $streaming,
            ]);
            $this->events->dispatch(new ResponseReceivedFromLLM($response->getStatusCode()));
            return $response;
        } catch (Exception $e) {
            $this->events->dispatch(new RequestToLLMFailed($url, $method, $headers, $body, $e->getMessage()));
            throw $e;
        }
    }

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