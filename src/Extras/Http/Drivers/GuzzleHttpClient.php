<?php

namespace Cognesy\Instructor\Extras\Http\Drivers;

use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Extras\Debug\Debug;
use Cognesy\Instructor\Extras\Http\Contracts\CanHandleHttp;
use Cognesy\Instructor\Extras\Http\Data\HttpClientConfig;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class GuzzleHttpClient implements CanHandleHttp
{
    protected Client $client;

    public function __construct(
        protected HttpClientConfig $config,
        protected ?EventDispatcher $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        if (isset($this->httpClient) && Debug::isEnabled()) {
            throw new InvalidArgumentException("Guzzle does not allow to inject debugging stack into existing client. Turn off debug or use default client.");
        }
        $this->client = match(Debug::isEnabled()) {
            false => new Client(),
            true => new Client(['handler' => $this->addDebugStack(HandlerStack::create())]),
        };
    }

    public function handle(
        string $url,
        array $headers,
        array $body,
        string $method = 'POST',
        bool $streaming = false
    ) : ResponseInterface {
        return $this->client->request($method, $url, [
            'headers' => $headers,
            'json' => $body,
            'connect_timeout' => $this->config->connectTimeout ?? 3,
            'timeout' => $this->config->requestTimeout ?? 30,
            'debug' => Debug::isFlag('http.trace') ?? false,
            'stream' => $streaming,
        ]);
    }

    protected function addDebugStack(HandlerStack $stack) : HandlerStack {
        $stack->push(Middleware::tap(
            function (RequestInterface $request, $options) {
                Debug::tryDumpRequest($request);
                Debug::tryDumpTrace();
            },
            function ($request, $options, FulfilledPromise|RejectedPromise $response) {
                $response->then(function (ResponseInterface $response) use ($request, $options) {
                    Debug::tryDumpResponse($response, $options);
                });
            })
        );
        return $stack;
    }
}