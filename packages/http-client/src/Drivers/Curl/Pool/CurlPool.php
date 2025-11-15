<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Curl\Pool;

use Cognesy\Http\Collections\HttpRequestList;
use Cognesy\Http\Collections\HttpResponseList;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleRequestPool;
use Cognesy\Http\Drivers\Curl\CurlFactory;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;

/**
 * CurlPool - Clean Pool Implementation for Concurrent Requests
 *
 * Orchestrates concurrent HTTP requests using curl_multi with a rolling window strategy.
 *
 * Architecture:
 * - CurlFactory: Handle configuration (shared with CurlDriver)
 * - CurlMultiRunner: Event loop execution (testable in isolation)
 * - PoolState: Execution state (encapsulates queues and collections)
 * - ActiveTransfers/PoolResponses: Type-safe collections (no arrays-as-collections)
 * - CurlErrorMapper: Error translation (shared with CurlDriver)
 *
 * Benefits:
 * - Clear separation of concerns (orchestration vs execution vs state)
 * - Testable components (runner is isolated and unit-testable)
 * - Robust curl_multi loop (handles CURLM_CALL_MULTI_PERFORM, select -1, etc.)
 * - Type-safe collections (no magic array keys)
 * - Minimal parameter passing (state object encapsulates everything)
 */
final class CurlPool implements CanHandleRequestPool
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
            throw new InvalidArgumentException('CurlPool does not support external client instances');
        }

        $this->runner = new CurlMultiRunner(
            factory: new CurlFactory($config),
            config: $config,
            events: $events,
        );
    }

    #[\Override]
    public function pool(HttpRequestList $requests, ?int $maxConcurrent = null): HttpResponseList {
        if ($requests->isEmpty()) {
            return HttpResponseList::empty();
        }

        $maxConcurrent = $maxConcurrent ?? $this->config->maxConcurrent ?? 5;

        // Create execution state
        $state = new PoolState(
            requests: $requests->all(),
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
        return HttpResponseList::fromArray($state->responses->finalize());
    }
}
