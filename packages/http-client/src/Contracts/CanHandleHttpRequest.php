<?php
namespace Cognesy\Http\Contracts;

use Cognesy\Http\Data\HttpRequest;

/**
 * Interface CanHandleHttp
 *
 * Defines the contract for an HTTP client handler implemented by various HTTP clients
 */
interface CanHandleHttpRequest
{
    /**
     * Handle an HTTP request
     *
     * @param HttpRequest $request
     * @return HttpResponse
     */
    public function handle(HttpRequest $request) : HttpResponse;
}
