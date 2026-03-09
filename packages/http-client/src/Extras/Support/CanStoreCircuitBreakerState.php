<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Support;

interface CanStoreCircuitBreakerState
{
    public function load(string $key): ?CircuitBreakerState;

    public function save(string $key, CircuitBreakerState $state): void;
}
