<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Laravel;

use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleRequestPool;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Events\HttpRequestFailed;
use Cognesy\Http\Events\HttpResponseReceived;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Utils\Result\Result;
use Exception;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;

class LaravelPool implements CanHandleRequestPool
{
    public function __construct(
        protected HttpFactory $factory,
        protected EventDispatcherInterface $events,
        protected HttpClientConfig $config,
    ) {}

    public function pool(array $requests, ?int $maxConcurrent = null): array {
        $maxConcurrent = $maxConcurrent ?? $this->config->maxConcurrent;
        $responses = [];
        $batches = array_chunk($requests, $maxConcurrent);

        foreach ($batches as $batch) {
            $batchResponses = $this->processBatch($batch);
            $processedResponses = $this->processBatchResponses($batchResponses);
            foreach ($processedResponses as $response) {
                $responses[] = $response;
            }
        }

        return $responses;
    }

    private function processBatch(array $batch): array {
        return $this->factory->pool(function (Pool $pool) use ($batch) {
            return $this->createPoolRequests($pool, $batch);
        });
    }

    private function createPoolRequests(Pool $pool, array $batch): array {
        $poolRequests = [];
        foreach ($batch as $request) {
            if (!$request instanceof HttpRequest) {
                throw new InvalidArgumentException('Invalid request type in pool');
            }
            $poolRequests[] = $this->createPoolRequest($pool, $request);
        }
        return $poolRequests;
    }

    private function createPoolRequest(Pool $pool, HttpRequest $request) {
        return $pool->withOptions([
            'timeout' => $this->config->requestTimeout,
            'connect_timeout' => $this->config->connectTimeout,
            'headers' => $request->headers(),
        ])->{strtolower($request->method())}(
            $request->url(),
            $request->method() === 'GET' ? [] : $request->body()->toArray(),
        );
    }

    private function processBatchResponses(array $batchResponses): array {
        $responses = [];
        foreach ($batchResponses as $response) {
            $responses[] = match(true) {
                $response instanceof Exception => $this->handleException($response),
                $response instanceof Response && $response->failed() => $this->handleFailedResponse($response),
                $response instanceof Response => $this->handleSuccessfulResponse($response),
                default => throw new InvalidArgumentException('Invalid response type in pool'),
            };
        }
        return $responses;
    }

    private function handleSuccessfulResponse(Response $response): Result {
        $this->events->dispatch(new HttpResponseReceived($response->status()));
        return Result::success(new LaravelHttpResponse(
            response: $response,
            events: $this->events,
            streaming: false,
            streamChunkSize: $this->config->streamChunkSize
        ));
    }

    private function handleFailedResponse(Response $response): Result {
        $errorMessage = sprintf('HTTP %d: %s', $response->status(), $response->body());
        return match($this->config->failOnError) {
            true => throw new HttpRequestException($errorMessage),
            default => Result::failure(new HttpRequestException($errorMessage)),
        };
    }

    private function handleException(Exception $exception): Result {
        if ($this->config->failOnError) {
            throw $exception;
        }

        $this->events->dispatch(new HttpRequestFailed([
            'url' => 'unknown',
            'method' => 'unknown',
            'headers' => [],
            'body' => [],
            'errors' => $exception->getMessage(),
        ]));
        return Result::failure($exception);
    }
}