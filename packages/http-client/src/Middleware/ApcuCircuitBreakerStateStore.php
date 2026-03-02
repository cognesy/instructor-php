<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware;

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
    public function load(string $key): ?array {
        if (!self::isSupported()) {
            return null;
        }

        $success = false;
        /** @var mixed $state */
        $state = apcu_fetch($this->cacheKey($key), $success);
        if (!$success || !is_array($state)) {
            return null;
        }
        return $state;
    }

    #[\Override]
    public function save(string $key, array $state): void {
        if (!self::isSupported()) {
            return;
        }

        apcu_store($this->cacheKey($key), $state);
    }

    private function cacheKey(string $key): string {
        return $this->prefix . sha1($key);
    }
}
