<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware;

final class InMemoryCircuitBreakerStateStore implements CanStoreCircuitBreakerState
{
    /** @var array<string, CircuitBreakerState> */
    private array $circuits = [];

    #[\Override]
    public function load(string $key): ?CircuitBreakerState {
        return $this->circuits[$key] ?? null;
    }

    #[\Override]
    public function save(string $key, CircuitBreakerState $state): void {
        $this->circuits[$key] = $state;
    }
}
