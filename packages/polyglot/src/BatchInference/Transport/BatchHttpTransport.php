<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Transport;

use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Polyglot\BatchInference\Config\BatchReadRetryPolicy;
use Cognesy\Polyglot\BatchInference\Contracts\CanDelayBatchRead;
use Cognesy\Polyglot\BatchInference\Exceptions\BatchException;
use Cognesy\Polyglot\BatchInference\Exceptions\BatchHttpException;
use JsonException;
use Throwable;

final readonly class BatchHttpTransport
{
    private BatchReadRetryPolicy $readRetry;
    private CanDelayBatchRead $delay;

    public function __construct(
        private CanSendHttpRequests $http,
        ?BatchReadRetryPolicy $readRetry = null,
        ?CanDelayBatchRead $delay = null,
    ) {
        $this->readRetry = $readRetry ?? new BatchReadRetryPolicy();
        $this->delay = $delay ?? new SystemBatchReadDelay();
    }

    /**
     * @param array<string, string> $headers
     * @param ?array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function json(string $method, string $url, array $headers, ?array $body = null): array
    {
        $decoded = $this->decode($method, $url, $headers, $body);
        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new BatchException('Batch API must return a JSON object.');
        }

        return $decoded;
    }

    /**
     * @param array<string, string> $headers
     * @return list<mixed>
     */
    public function jsonList(string $method, string $url, array $headers): array
    {
        $decoded = $this->decode($method, $url, $headers);
        if (!is_array($decoded) || !array_is_list($decoded)) {
            throw new BatchException('Batch API must return a JSON list.');
        }

        return $decoded;
    }

    /** @param array<string, string> $headers */
    public function response(string $method, string $url, array $headers, string $body = ''): HttpResponse
    {
        return $this->send(new HttpRequest($url, $method, $headers, $body, []));
    }

    /** @param array<string, string> $headers */
    public function ack(string $method, string $url, array $headers): void
    {
        $this->response($method, $url, $headers);
    }

    /** @param array<string, string> $headers
     *  @param ?array<string, mixed> $body
     */
    private function decode(string $method, string $url, array $headers, ?array $body = null): mixed
    {
        $payload = $body === null ? '' : json_encode($body, JSON_THROW_ON_ERROR);
        $response = $this->response($method, $url, $headers, $payload);
        try {
            $decoded = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new BatchException('Batch API returned invalid JSON.', previous: $error);
        }
        return $decoded;
    }

    /**
     * @param array<string, string> $headers
     * @return iterable<string>
     */
    public function stream(string $url, array $headers): iterable
    {
        $response = $this->send(new HttpRequest($url, 'GET', $headers, '', ['stream' => true]));
        yield from $response->stream();
    }

    private function send(HttpRequest $request): HttpResponse
    {
        $attempt = 0;
        while (true) {
            $attempt++;
            try {
                $response = $this->http->send($request)->get();
                $this->assertSuccessful($response->statusCode());
                return $response;
            } catch (BatchHttpException|HttpRequestException $error) {
                if ($request->method() !== 'GET' || !$this->canRetry($error, $attempt)) {
                    throw $error;
                }
                $this->delay->wait($this->readRetry->delayAfter($attempt));
            }
        }
    }

    private function canRetry(Throwable $error, int $attempt): bool
    {
        if ($attempt >= $this->readRetry->maxAttempts()) {
            return false;
        }
        if ($error instanceof HttpRequestException) {
            return $error->isRetriable();
        }
        if ($error instanceof BatchHttpException) {
            $status = $error->statusCode();
            return $status === 408 || $status === 429 || $status >= 500;
        }
        return false;
    }

    private function assertSuccessful(int $status): void
    {
        if ($status < 200 || $status >= 300) {
            throw new BatchHttpException($status);
        }
    }
}
