<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Curl\Pool;

use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Drivers\Curl\CurlErrorMapper;
use Cognesy\Http\Drivers\Curl\CurlFactory;
use Cognesy\Http\Drivers\Curl\HeaderParser;
use Cognesy\Http\Exceptions\HttpExceptionFactory;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Utils\Result\Result;
use CurlMultiHandle;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Robust curl_multi event loop runner
 *
 * Encapsulates the curl_multi execution loop with proper handling of:
 * - CURLM_CALL_MULTI_PERFORM (continue instead of breaking)
 * - curl_multi_select returning -1 (use usleep to avoid busy loop)
 * - Draining all messages from curl_multi_info_read
 * - Rolling window concurrency management
 *
 * This class is unit-testable in isolation from the pool orchestrator.
 */
final class CurlMultiRunner
{
    private readonly CurlErrorMapper $errorMapper;

    public function __construct(
        private readonly CurlFactory $factory,
        private readonly HttpClientConfig $config,
        private readonly EventDispatcherInterface $events,
    ) {
        $this->errorMapper = new CurlErrorMapper();
    }

    /**
     * Execute pool with rolling window concurrency
     *
     * @param CurlMultiHandle $multiHandle The curl_multi handle to use
     * @param PoolState $state The pool execution state
     */
    public function execute(CurlMultiHandle $multiHandle, PoolState $state): void {
        // Fill initial window
        $this->fillWindow($multiHandle, $state);

        // Event loop
        $this->driveEventLoop($multiHandle, $state);
    }

    /**
     * Fill the rolling window up to max concurrency
     */
    public function fillWindow(CurlMultiHandle $multiHandle, PoolState $state): void {
        while ($state->hasMoreRequests() && $state->activeTransfers->hasCapacity($state->maxConcurrent)) {
            $request = $state->nextRequest();
            if ($request === null) {
                break;
            }

            $this->attachRequest($multiHandle, $request, $state->currentIndex(), $state);
        }
    }

    /**
     * Drive the curl_multi event loop until completion
     */
    private function driveEventLoop(CurlMultiHandle $multiHandle, PoolState $state): void {
        do {
            $status = curl_multi_exec($multiHandle, $stillRunning);

            // Handle CURLM_CALL_MULTI_PERFORM - continue instead of breaking
            if ($status === CURLM_CALL_MULTI_PERFORM) {
                continue;
            }

            if ($status !== CURLM_OK) {
                break;
            }

            // Process all completed transfers
            $this->processCompletedTransfers($multiHandle, $state);

            // Wait for activity if there are still running transfers
            if ($stillRunning > 0) {
                $this->waitForActivity($multiHandle);
            }

        } while ($stillRunning > 0 || !$state->activeTransfers->isEmpty());
    }

    /**
     * Wait for curl_multi activity with robust fallback
     *
     * Handles curl_multi_select returning -1 by using a short sleep
     * to avoid busy loops.
     */
    private function waitForActivity(CurlMultiHandle $multiHandle): void {
        $selected = curl_multi_select($multiHandle, 0.1);

        // curl_multi_select can return -1 on some systems/conditions
        // Use short sleep to avoid busy loop
        if ($selected === -1) {
            usleep(1000); // 1ms sleep to avoid busy loop
        }
    }

    /**
     * Process all completed transfers
     *
     * Drains all messages from curl_multi_info_read and processes each completion.
     */
    private function processCompletedTransfers(CurlMultiHandle $multiHandle, PoolState $state): void {
        // Drain all messages - don't stop at first one
        while ($info = curl_multi_info_read($multiHandle)) {
            if ($info['msg'] !== CURLMSG_DONE) {
                continue;
            }

            $nativeHandle = $info['handle'];
            $transfer = $state->activeTransfers->getByNativeHandle($nativeHandle);

            if ($transfer === null) {
                continue; // Already processed or unknown handle
            }

            // Process completion
            try {
                $response = $this->createResponse($transfer);

                // For pools, check status code and create Failure for error responses
                // regardless of failOnError setting (which only controls throwing)
                if ($response->statusCode() >= 400) {
                    $exception = HttpExceptionFactory::fromStatusCode(
                        $response->statusCode(),
                        $transfer->request,
                        $response,
                        null
                    );

                    if ($this->config->failOnError) {
                        throw $exception;
                    }

                    $state->responses->set($transfer->index, Result::failure($exception));
                } else {
                    $state->responses->set($transfer->index, Result::success($response));
                }
            } catch (\Throwable $e) {
                if ($this->config->failOnError) {
                    throw $e;
                }
                $state->responses->set($transfer->index, Result::failure($e));
            }

            // Cleanup and refill window
            $this->detachTransfer($multiHandle, $transfer, $state);
            $this->fillWindow($multiHandle, $state);
        }
    }

    /**
     * Attach a new request to the multi handle
     */
    private function attachRequest(
        CurlMultiHandle $multiHandle,
        HttpRequest $request,
        int $requestIndex,
        PoolState $state,
    ): void {
        $handle = $this->factory->createHandle($request);
        $parser = new HeaderParser();

        // Configure for pooled execution
        $handle->setOption(CURLOPT_RETURNTRANSFER, true);
        $handle->setOption(CURLOPT_HEADERFUNCTION, fn($_, $line) => $this->parseHeader($parser, $line));

        curl_multi_add_handle($multiHandle, $handle->native());

        $transfer = new ActiveTransfer($handle, $parser, $request, $requestIndex);
        $state->activeTransfers->add($transfer);

        $this->dispatchRequestSent($request);
    }

    /**
     * Detach transfer from multi handle and cleanup
     */
    private function detachTransfer(
        CurlMultiHandle $multiHandle,
        ActiveTransfer $transfer,
        PoolState $state,
    ): void {
        curl_multi_remove_handle($multiHandle, $transfer->handle->native());
        $state->activeTransfers->removeByHandle($transfer->handle);
        // CurlHandle destructor will close the handle
    }

    /**
     * Create response from completed transfer
     *
     * Note: For pooled requests with curl_multi, we use PoolCurlResponseAdapter
     * which retrieves the body via curl_multi_getcontent.
     */
    private function createResponse(ActiveTransfer $transfer): HttpResponse {
        // Check for curl errors
        if ($transfer->handle->errorCode() !== 0) {
            $this->handleCurlError($transfer);
        }

        $adapter = new PoolCurlResponseAdapter(
            handle: $transfer->handle,
            headerParser: $transfer->parser,
        );
        $response = $adapter->toHttpResponse();

        $this->validateStatusCodeOrFail($response, $transfer->request);
        $this->dispatchResponseReceived($response->statusCode());

        return $response;
    }

    /**
     * Handle curl errors using the error mapper
     */
    private function handleCurlError(ActiveTransfer $transfer): never {
        $exception = $this->errorMapper->mapError(
            $transfer->handle->errorCode(),
            $transfer->handle->error() ?? 'Unknown error',
            $transfer->request,
        );

        $this->dispatchRequestFailed($exception, $transfer->request);
        throw $exception;
    }

    /**
     * Validate HTTP status code and throw if needed
     */
    private function validateStatusCodeOrFail(HttpResponse $response, HttpRequest $request): void {
        if (!$this->config->failOnError || $response->statusCode() < 400) {
            return;
        }

        $exception = HttpExceptionFactory::fromStatusCode(
            $response->statusCode(),
            $request,
            $response,
            null
        );

        $this->dispatchRequestFailed($exception, $request);
        throw $exception;
    }

    /**
     * Parse header callback
     */
    private function parseHeader(HeaderParser $parser, string $line): int {
        $parser->parse($line);
        return strlen($line);
    }

    // Event dispatching - these will move to AbstractPool later
    private function dispatchRequestSent(HttpRequest $request): void {
        $this->events->dispatch(new \Cognesy\Http\Events\HttpRequestSent([
            'url' => $request->url(),
            'method' => $request->method(),
            'headers' => $request->headers(),
            'body' => $request->body()->toArray(),
        ]));
    }

    private function dispatchResponseReceived(int $statusCode): void {
        $this->events->dispatch(new \Cognesy\Http\Events\HttpResponseReceived(['statusCode' => $statusCode]));
    }

    private function dispatchRequestFailed(HttpRequestException $exception, HttpRequest $request): void {
        $this->events->dispatch(new \Cognesy\Http\Events\HttpRequestFailed([
            'url' => $request->url(),
            'method' => $request->method(),
            'errors' => $exception->getMessage(),
        ]));
    }
}
