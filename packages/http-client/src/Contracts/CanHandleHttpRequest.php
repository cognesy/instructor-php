<?php
namespace Cognesy\Http\Contracts;

use Cognesy\Http\Data\HttpClientRequest;

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
     * @param HttpClientRequest $request
     * @return HttpClientResponse
     */
    public function handle(HttpClientRequest $request) : HttpClientResponse;
}
