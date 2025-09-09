<?php

namespace Middleware\RetryMiddleware;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Http\Middleware\Base\BaseMiddleware;

class RetryMiddleware extends BaseMiddleware
{
    private int $maxRetries;
    private int $retryDelay;
    private array $retryStatusCodes;

    public function __construct(
        int $maxRetries = 3,
        int $retryDelay = 1,
        array $retryStatusCodes = [429, 500, 502, 503, 504]
    ) {
        $this->maxRetries = $maxRetries;
        $this->retryDelay = $retryDelay;
        $this->retryStatusCodes = $retryStatusCodes;
    }

    public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse
    {
        $attempts = 0;

        while (true) {
            try {
                $attempts++;
                $response = $next->handle($request);

                // If we got a response with a status code we should retry on
                if (in_array($response->statusCode(), $this->retryStatusCodes) && $attempts <= $this->maxRetries) {
                    $this->delay($attempts);
                    continue;
                }

                return $response;

            } catch (HttpRequestException $e) {
                // If we've exceeded our retry limit, rethrow the exception
                if ($attempts >= $this->maxRetries) {
                    throw $e;
                }

                // Otherwise, wait and try again
                $this->delay($attempts);
            }
        }
    }

    private function delay(int $attempt): void
    {
        // Exponential backoff: 1s, 2s, 4s, 8s, etc.
        $sleepTime = $this->retryDelay * (2 ** ($attempt - 1));
        sleep($sleepTime);
    }
}
