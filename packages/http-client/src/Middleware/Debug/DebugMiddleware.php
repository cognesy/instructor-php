<?php

namespace Cognesy\Http\Middleware\Debug;

use Cognesy\Http\BaseMiddleware;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Utils\Debug\Debug;
use Cognesy\Utils\Events\EventDispatcher;

/**
 * DebugMiddleware
 *
 * A middleware that provides debugging capabilities for HTTP requests and responses.
 * It can be used with any HTTP driver that implements the CanHandleHttp interface.
 */
class DebugMiddleware extends BaseMiddleware
{
    public function __construct(
        protected ?Debug $debug = null,
        protected ?EventDispatcher $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->debug = $debug ?? new Debug();
    }

    protected function beforeRequest(HttpClientRequest $request): void {
        $this->debug->tryDumpUrl($request->url());
        $this->debug->tryDumpRequest($request);
        $this->debug->tryDumpTrace();
    }

    protected function afterRequest(
        HttpClientRequest  $request,
        HttpClientResponse $response
    ): HttpClientResponse {
        $this->debug->tryDumpResponse($response, ['stream' => $request->isStreamed()]);
        return $response;
    }

    protected function shouldDecorateResponse(
        HttpClientRequest  $request,
        HttpClientResponse $response,
    ): bool {
        return true; //$request->isStreamed();
    }

    protected function toResponse(
        HttpClientRequest  $request,
        HttpClientResponse $response
    ): HttpClientResponse {
        return new DebugResponseDecorator($request, $response);
    }
}