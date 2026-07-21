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
use Cognesy\Polyglot\Inference\Core\SensitiveDataRedactor;
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
        return SensitiveDataRedactor::redactHeaders($headers);
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function redactedValues(array $data): array {
        return SensitiveDataRedactor::redactValues($data);
    }

    private function redactedUrl(string $url): string {
        return SensitiveDataRedactor::redactUrl($url);
    }

    private function redactedExceptionMessage(Throwable $source, HttpRequest $request): string {
        return SensitiveDataRedactor::redactUrlInText($source->getMessage(), $request->url());
    }
}
