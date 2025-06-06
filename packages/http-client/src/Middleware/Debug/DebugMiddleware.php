<?php

namespace Cognesy\Http\Middleware\Debug;

use Cognesy\Http\BaseMiddleware;
use Cognesy\Http\Config\DebugConfig;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * DebugMiddleware
 *
 * A middleware that provides debugging capabilities for HTTP requests and responses.
 * It can be used with any HTTP driver that implements the CanHandleHttp interface.
 */
class DebugMiddleware extends BaseMiddleware
{
    private Debug $debug;

    public function __construct(
        protected DebugConfig $config,
        protected EventDispatcherInterface $events,
    ) {
        $this->debug = new Debug(
            //new ConsoleDebug($this->config),
            new EventsDebug($this->config, $this->events),
        );
    }

    protected function beforeRequest(HttpClientRequest $request): void {
        $this->debug->handleRequest($request);
    }

    protected function afterRequest(
        HttpClientRequest  $request,
        HttpClientResponse $response
    ): HttpClientResponse {
        $this->debug->handleResponse($response, ['stream' => $request->isStreamed()]);
        return $response;
    }

    protected function shouldDecorateResponse(
        HttpClientRequest  $request,
        HttpClientResponse $response,
    ): bool {
        return $request->isStreamed();
    }

    protected function toResponse(
        HttpClientRequest  $request,
        HttpClientResponse $response
    ): HttpClientResponse {
        return new DebugResponseDecorator($request, $response, $this->debug, !$this->config->httpResponseStreamByLine);
    }
}