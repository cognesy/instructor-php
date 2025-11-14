<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\CurlNew;

use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleRequestPool;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;

/**
 * CurlNewPool - Clean Pool Implementation for Concurrent Requests
 *
 * Orchestrates concurrent HTTP requests using curl_multi with a rolling window strategy.
 *
 * Architecture:
 * - CurlFactory: Handle configuration (shared with CurlNewDriver)
 * - CurlMultiRunner: Event loop execution (testable in isolation)
 * - PoolState: Execution state (encapsulates queues and collections)
 * - ActiveTransfers/PoolResponses: Type-safe collections (no arrays-as-collections)
 * - CurlErrorMapper: Error translation (shared with CurlNewDriver)
 *
 * Benefits:
 * - Clear separation of concerns (orchestration vs execution vs state)
 * - Testable components (runner is isolated and unit-testable)
 * - Robust curl_multi loop (handles CURLM_CALL_MULTI_PERFORM, select -1, etc.)
 * - Type-safe collections (no magic array keys)
 * - Minimal parameter passing (state object encapsulates everything)
 */
final class CurlNewPool implements CanHandleRequestPool
{
    private readonly CurlMultiRunner $runner;

    public function __construct(
        private readonly HttpClientConfig $config,
        private readonly EventDispatcherInterface $events,
        ?object $clientInstance = null,
    ) {
        if (!extension_loaded('curl')) {
            throw new RuntimeException('cURL extension is not loaded');
        }

        if ($clientInstance !== null) {
            throw new InvalidArgumentException('CurlNewPool does not support external client instances');
        }

        $this->runner = new CurlMultiRunner(
            factory: new CurlFactory($config),
            config: $config,
            events: $events,
        );
    }

    #[\Override]
    public function pool(array $requests, ?int $maxConcurrent = null): array {
        if (empty($requests)) {
            return [];
        }

        $maxConcurrent = $maxConcurrent ?? $this->config->maxConcurrent ?? 5;

        // Create execution state
        $state = new PoolState(
            requests: $requests,
            maxConcurrent: $maxConcurrent,
            activeTransfers: new ActiveTransfers(),
            responses: new PoolResponses(),
        );

        // Execute with curl_multi
        $multiHandle = curl_multi_init();

        try {
            $this->runner->execute($multiHandle, $state);
        } finally {
            curl_multi_close($multiHandle);
        }

        // Return responses in original request order
        return $state->responses->finalize();
    }
}
