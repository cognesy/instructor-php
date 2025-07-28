<?php
namespace Middleware\AuthenticationMiddleware;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Middleware\Base\BaseMiddleware;

class AuthenticationMiddleware extends BaseMiddleware
{
    private $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    protected function beforeRequest(HttpRequest $request): void
    {
        // Add authorization header to the request
        $headers = $request->headers();
        $headers['Authorization'] = 'Bearer ' . $this->apiKey;

        // Note: In a real implementation, you would need to create a new request
        // with the updated headers, as HttpRequest is immutable
    }

    protected function afterRequest(
        HttpRequest $request,
        HttpResponse $response
    ): HttpResponse {
        // Check if the response indicates an authentication error
        if ($response->statusCode() === 401) {
            // Log or handle authentication failures
            error_log('Authentication failed: ' . $response->body());
        }

        return $response;
    }
}
