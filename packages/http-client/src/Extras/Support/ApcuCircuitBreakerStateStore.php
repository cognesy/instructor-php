<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Support;

final readonly class ApcuCircuitBreakerStateStore implements CanStoreCircuitBreakerState
{
    public function __construct(
        private string $prefix = 'cognesy_http_circuit_',
    ) {}

    public static function isSupported(): bool {
        if (!function_exists('apcu_fetch') || !function_exists('apcu_store')) {
            return false;
        }

        $enabled = strtolower((string) ini_get('apc.enabled'));
        return $enabled === '1' || $enabled === 'on';
    }

    #[\Override]
    public function load(string $key): ?CircuitBreakerState {
        if (!self::isSupported()) {
            return null;
        }

        $success = false;
        /** @var mixed $storedState */
        $storedState = apcu_fetch($this->cacheKey($key), $success);
        if (!$success) {
            return null;
        }

        if ($storedState instanceof CircuitBreakerState) {
            return $storedState;
        }

        if (!is_array($storedState)) {
            return null;
        }

        return CircuitBreakerState::fromArray($storedState);
    }

    #[\Override]
    public function save(string $key, CircuitBreakerState $state): void {
        if (!self::isSupported()) {
            return;
        }

        apcu_store($this->cacheKey($key), $state->toArray());
    }

    private function cacheKey(string $key): string {
        return $this->prefix . sha1($key);
    }
}
