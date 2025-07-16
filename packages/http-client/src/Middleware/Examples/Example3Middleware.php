<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware\Examples;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Middleware\Base\BaseMiddleware;

abstract class Example3Middleware extends BaseMiddleware
{
    /**
     * beforeRequest() is called right before we send the request downstream.
     * Override to do logging, modify headers, measure start times, etc.
     */
    protected function beforeRequest(HttpRequest $request): HttpRequest {
        // This is where you can modify the request before sending it
        // For example, you might want to add custom headers or log the request
        // $request->headers['X-Custom-Header'] = 'Value';
        // You can also log the request details here if needed
        return $request;
    }

    /**
     * afterRequest() is called once we have the raw response from the next handler.
     * Override to transform or log the response before returning it.
     */
    protected function afterRequest(HttpRequest $request, HttpResponse $response): HttpResponse {
        if ($this->shouldDecorateResponse($request, $response)) {
            return $this->toResponse($request, $response);
        }
        return $response;
    }

    /**
     * Should we decorate the response to intercept streaming chunks or do
     * additional transformations? By default, returns false. Subclasses
     * can override this to conditionally enable decoration.
     */
    protected function shouldDecorateResponse(HttpRequest $request, HttpResponse $response,): bool {
        return false;
    }

    /**
     * decorateResponse() is called if shouldDecorateResponse() returns true.
     * Default implementation wraps the response in a basic chunk-decorating class
     * that calls processChunk() on every chunk. Override if you need custom logic.
     */
    protected function toResponse(HttpRequest $request, HttpResponse $response): HttpResponse {
        return $response;
    }
}