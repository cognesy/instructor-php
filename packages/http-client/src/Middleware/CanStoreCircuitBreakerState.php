<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware;

interface CanStoreCircuitBreakerState
{
    /**
     * @return array{state: string, failures: int, lastFailure: int, halfOpenRequests: int, halfOpenSuccesses: int}|null
     */
    public function load(string $key): ?array;

    /**
     * @param array{state: string, failures: int, lastFailure: int, halfOpenRequests: int, halfOpenSuccesses: int} $state
     */
    public function save(string $key, array $state): void;
}
