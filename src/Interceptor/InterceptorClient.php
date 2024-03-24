<?php
namespace Cognesy\Instructor\Interceptor;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class InterceptorClient
{
    static private InterceptorClient $instance;
    public int $connectTimeout = 3;
    public int $timeout = 30;
    protected array $streamProcessors = [];
    protected array $processors = [];

    public static function getClient(): ClientInterface
    {
        if (!isset(self::$instance)) {
            self::$instance = new static();
        }
        return self::$instance->makeInterceptor();
    }

    /////////////////////////////////////////////////////////////////////////////////////////

    protected function addProcessor($processor) {
        $this->processors[] = $processor;
    }

    protected function addStreamProcessor($processor) {
        $this->streamProcessors[] = $processor;
    }

    /////////////////////////////////////////////////////////////////////////////////////////

    protected function makeInterceptor() {
        $preprocessResponseMiddleware = function (callable $handler) {
            return function ($request, array $options) use ($handler) {
                $promise = $handler($request, $options);
                $isStreamEnabled = $this->isStreamEnabled($options);
                $processor = match($isStreamEnabled) {
                    false => fn(ResponseInterface $response) => $this->preprocessResponse($response),
                    default => fn(ResponseInterface $response) => $this->preprocessStreamResponse($response),
                };
                return $promise->then($processor);
            };
        };
        $stack = HandlerStack::create();
        $stack->push($preprocessResponseMiddleware);
        return new Client([
            'handler' => $stack,
            'connect_timeout' => $this->connectTimeout,
            'timeout' => $this->timeout,
        ]);
    }

    protected function preprocessResponse(ResponseInterface $response) : ResponseInterface {
        foreach ($this->processors as $processor) {
            $response = $processor->process($response);
        }
        return $response;
    }

    protected function preprocessStreamResponse(ResponseInterface $response) : ResponseInterface {
        foreach ($this->streamProcessors as $processor) {
            $response = $processor->process($response);
        }
        return $response;
    }

    protected function isStreamEnabled(array $options) : bool {
        return isset($options['stream']) && ($options['stream'] === true);
    }

    protected function isStreamResponse(ResponseInterface $response) : bool {
        $body = $response->getBody();
        return $body instanceof Stream || $body instanceof StreamInterface;
    }
}
