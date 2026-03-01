<?php declare(strict_types=1);

namespace Middleware\RetryMiddleware;

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\HttpClient;
use Cognesy\Http\Middleware\RetryPolicy;

final class RetryingClientFactory
{
    public static function make(): HttpClient
    {
        return (new HttpClientBuilder())
            ->withRetryPolicy(new RetryPolicy(maxRetries: 3, baseDelayMs: 200))
            ->create();
    }
}
