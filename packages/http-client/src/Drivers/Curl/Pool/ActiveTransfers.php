<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Curl\Pool;

use Cognesy\Http\Drivers\Curl\CurlHandle;

/**
 * Collection of active HTTP transfers in curl_multi
 *
 * Manages the lifecycle of active transfers, providing type-safe
 * operations instead of using arrays with magic keys.
 *
 * Uses spl_object_id of the native curl handle as the internal key
 * for O(1) lookups when curl_multi_info_read returns a handle.
 */
final class ActiveTransfers
{
    /** @var array<int, ActiveTransfer> Indexed by spl_object_id(handle->native()) */
    private array $byId = [];

    /**
     * Add a new active transfer
     */
    public function add(ActiveTransfer $transfer): void {
        $handleId = spl_object_id($transfer->handle->native());
        $this->byId[$handleId] = $transfer;
    }

    /**
     * Get transfer by native curl handle
     *
     * @param \CurlHandle $nativeHandle The native curl handle from curl_multi_info_read
     * @return ActiveTransfer|null The transfer if found, null otherwise
     */
    public function getByNativeHandle(\CurlHandle $nativeHandle): ?ActiveTransfer {
        $handleId = spl_object_id($nativeHandle);
        return $this->byId[$handleId] ?? null;
    }

    /**
     * Remove a transfer by its CurlHandle wrapper
     */
    public function removeByHandle(CurlHandle $handle): void {
        $handleId = spl_object_id($handle->native());
        unset($this->byId[$handleId]);
    }

    /**
     * Get the number of active transfers
     */
    public function count(): int {
        return count($this->byId);
    }

    /**
     * Check if there are any active transfers
     */
    public function isEmpty(): bool {
        return empty($this->byId);
    }

    /**
     * Check if there's room for more transfers given max concurrency
     */
    public function hasCapacity(int $maxConcurrent): bool {
        return $this->count() < $maxConcurrent;
    }
}
