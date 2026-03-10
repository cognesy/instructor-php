<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Drivers;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Polyglot\Embeddings\Contracts\EmbedRequestAdapter;
use Cognesy\Polyglot\Embeddings\Contracts\EmbedResponseAdapter;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsResponse;
use Cognesy\Polyglot\Embeddings\Events\EmbeddingsFailed;
use Cognesy\Polyglot\Embeddings\Events\EmbeddingsRequested;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use Throwable;

class BaseEmbedDriver implements CanHandleVectorization
{
    public function __construct(
        protected EmbeddingsConfig $config,
        protected CanSendHttpRequests $httpClient,
        protected EventDispatcherInterface $events,
        protected EmbedRequestAdapter $requestAdapter,
        protected EmbedResponseAdapter $responseAdapter
    ) {
    }

    /** @psalm-suppress InvalidReturnType, InvalidReturnStatement - Return type matches interface */
    #[\Override]
    public function handle(EmbeddingsRequest $request): HttpResponse {
        $clientRequest = $this->requestAdapter->toHttpClientRequest($request);
        $this->events->dispatch(new EmbeddingsRequested(['request' => $request->toArray()]));
        return $this->makeHttpResponse($clientRequest);
    }

    #[\Override]
    public function fromData(array $data): ?EmbeddingsResponse {
        return $this->responseAdapter->fromResponse($data);
    }

    // INTERNAL //////////////////////////////////////////////////////////////

    protected function makeHttpResponse(HttpRequest $request): HttpResponse {
        try {
            $httpResponse = $this->httpClient->send($request)->get();
        } catch (Exception $e) {
            $this->events->dispatch(new EmbeddingsFailed([
                'exception' => $this->redactedExceptionMessage($e, $request),
                'request' => $this->redactedRequest($request),
            ]));
            throw $e;
        }

        if ($httpResponse->statusCode() >= 400) {
            $this->events->dispatch(new EmbeddingsFailed([
                'statusCode' => $httpResponse->statusCode(),
                'headers' => $this->redactedHeaders($httpResponse->headers()),
                'body' => '[REDACTED]',
            ]));
            throw new HttpRequestException(
                message: 'HTTP request failed with status code ' . $httpResponse->statusCode(),
                request: $request,
                response: $httpResponse,
            );
        }
        return $httpResponse;
    }

    /**
     * @return array<string,mixed>
     */
    private function redactedRequest(HttpRequest $request): array {
        $payload = $request->toArray();
        $payload['url'] = $this->redactedUrl($request->url());
        $payload['headers'] = $this->redactedHeaders($request->headers());
        $payload['body'] = '[REDACTED]';
        if (isset($payload['options']) && is_array($payload['options'])) {
            $payload['options'] = $this->redactedValues($payload['options']);
        }

        return $payload;
    }

    /**
     * @param array<string,mixed> $headers
     * @return array<string,mixed>
     */
    private function redactedHeaders(array $headers): array {
        $redacted = [];
        foreach ($headers as $name => $value) {
            $redacted[$name] = $this->isSensitiveKey((string) $name)
                ? '[REDACTED]'
                : $value;
        }

        return $redacted;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function redactedValues(array $data): array {
        $redacted = [];
        foreach ($data as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                $redacted[$key] = '[REDACTED]';
                continue;
            }

            if (!is_array($value)) {
                $redacted[$key] = $value;
                continue;
            }

            $redacted[$key] = $this->redactedValues($value);
        }

        return $redacted;
    }

    private function redactedUrl(string $url): string {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['query'])) {
            return $url;
        }

        $parts['query'] = $this->redactedQuery($parts['query']);
        return $this->buildUrl($parts);
    }

    private function redactedQuery(string $query): string {
        $segments = explode('&', $query);
        $redacted = [];

        foreach ($segments as $segment) {
            if ($segment === '') {
                $redacted[] = $segment;
                continue;
            }

            [$rawKey, $rawValue] = array_pad(explode('=', $segment, 2), 2, null);
            $decodedKey = urldecode((string) $rawKey);
            if (!$this->isSensitiveKey($decodedKey)) {
                $redacted[] = $segment;
                continue;
            }

            $redacted[] = $rawKey . '=' . rawurlencode('[REDACTED]');
        }

        return implode('&', $redacted);
    }

    /**
     * @param array<string,mixed> $parts
     */
    private function buildUrl(array $parts): string {
        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $user = $parts['user'] ?? '';
        $pass = isset($parts['pass']) ? ':' . $parts['pass'] : '';
        $auth = $user !== '' ? $user . $pass . '@' : '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return $scheme . $auth . $host . $port . $path . $query . $fragment;
    }

    private function redactedExceptionMessage(Throwable $source, HttpRequest $request): string {
        return str_replace($request->url(), $this->redactedUrl($request->url()), $source->getMessage());
    }

    private function isSensitiveKey(string $key): bool {
        $normalized = strtolower(str_replace(['-', '_'], '', $key));

        if (in_array($normalized, ['apikey', 'authorization', 'proxyauthorization', 'token', 'accesstoken', 'refreshtoken', 'secret', 'password', 'cookie', 'setcookie'], true)) {
            return true;
        }

        if (str_contains($normalized, 'apikey')) {
            return true;
        }

        if (str_contains($normalized, 'authorization')) {
            return true;
        }

        if (str_contains($normalized, 'cookie')) {
            return true;
        }

        return str_contains($normalized, 'token')
            || str_contains($normalized, 'secret')
            || str_contains($normalized, 'password');
    }
}
