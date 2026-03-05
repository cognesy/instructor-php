<?php declare(strict_types=1);

namespace Troubleshooting\CircuitBreaker;

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\HttpClient;
use Cognesy\Http\Middleware\CircuitBreakerPolicy;

final class CircuitBreakerClientFactory
{
    public static function make(): HttpClient
    {
        return (new HttpClientBuilder())
            ->withCircuitBreakerPolicy(new CircuitBreakerPolicy(
                failureThreshold: 5,
                openForSec: 30,
            ))
            ->create();
    }
}
