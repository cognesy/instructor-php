<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware\Base;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;

/**
 * Class BaseMiddleware
 *
 * A convenient base class that implements the HttpMiddleware interface
 * and provides overridable hooks for common needs:
 * - beforeRequest() for pre-request logic
 * - afterRequest() for post-request logic / response transformation
 * - shouldDecorateResponse() to conditionally enable response wrapping
 * - decorateResponse() to wrap streaming or transform the response
 * - processChunk() to intercept each streamed chunk
 *
 * Subclasses only override what they need.
 */
abstract class BaseMiddleware implements HttpMiddleware
{
    /**
     * Main handle() method required by HttpMiddleware.
     *
     * This method implements a typical template:
     * 1) Run beforeRequest()
     * 2) Call the next handler to get the response
     * 3) Run afterRequest()
     * 4) If shouldDecorateResponse() is true, wrap the response
     * 5) Return the final response
     */
    public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse {
        if ($this->shouldExecute($request) === false) {
            // If the middleware decides not to execute, just pass the request
            // to the next handler without any modifications.
            return $next->handle($request);
        }

        // 1) Pre-request logic
        $request = $this->beforeRequest($request);

        // 2) Get the response from the next handler
        $response = $next->handle($request);

        // 3) Post-request logic, e.g. logging or rewriting
        $response = $this->afterRequest($request, $response);

        // 4) Optionally wrap the response if we want to intercept streaming
        if ($this->shouldDecorateResponse($request, $response)) {
            $response = $this->toResponse($request, $response);
        }

        // 5) Return the (possibly wrapped) response
        return $response;
    }

    /**
     * beforeRequest() is called right before we send the request downstream.
     * Override to do logging, modify headers, measure start times, etc.
     */
    protected function beforeRequest(HttpRequest $request): HttpRequest {
        // Default no-op
        return $request;
    }

    /**
     * afterRequest() is called once we have the raw response from the next handler.
     * Override to transform or log the response before returning it.
     */
    protected function afterRequest(HttpRequest $request, HttpResponse $response): HttpResponse {
        // Default: just return the response as-is
        return $response;
    }

    /**
     * Should we decorate the response to intercept streaming chunks or do
     * additional transformations? By default, returns false. Subclasses
     * can override this to conditionally enable decoration.
     */
    protected function shouldDecorateResponse(HttpRequest $request, HttpResponse $response): bool {
        return true;
    }

    /**
     * decorateResponse() is called if shouldDecorateResponse() returns true.
     * Default implementation wraps the response in a basic chunk-decorating class
     * that calls processChunk() on every chunk. Override if you need custom logic.
     */
    protected function toResponse(HttpRequest $request, HttpResponse $response): HttpResponse {
        // Here you can wrap the response in a class that intercepts streaming
        return $response;
    }

    protected function shouldExecute(HttpRequest $request) : bool {
        // Default implementation always executes the middleware.
        // Subclasses can override this to conditionally skip execution.
        return true;
    }
}
