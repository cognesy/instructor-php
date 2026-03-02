<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware;

final class InMemoryCircuitBreakerStateStore implements CanStoreCircuitBreakerState
{
    /** @var array<string, array{state: string, failures: int, lastFailure: int, halfOpenRequests: int, halfOpenSuccesses: int}> */
    private array $circuits = [];

    #[\Override]
    public function load(string $key): ?array {
        return $this->circuits[$key] ?? null;
    }

    #[\Override]
    public function save(string $key, array $state): void {
        $this->circuits[$key] = $state;
    }
}
