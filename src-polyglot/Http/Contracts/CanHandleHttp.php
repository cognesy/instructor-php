<?php
namespace Cognesy\Polyglot\Http\Contracts;

use Cognesy\Polyglot\Http\Data\HttpClientRequest;

/**
 * Interface CanHandleHttp
 *
 * Defines the contract for an HTTP client handler implemented by various HTTP clients
 */
interface CanHandleHttp
{
    /**
     * Handle an HTTP request
     *
     * @param HttpClientRequest $request
     * @return HttpClientResponse
     */
    public function handle(HttpClientRequest $request) : HttpClientResponse;
}
