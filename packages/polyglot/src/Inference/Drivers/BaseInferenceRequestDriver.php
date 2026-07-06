<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Http\Contracts\CanManageStreamCache;
use Cognesy\Http\Enums\StreamCachePolicy;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Http\Stream\StreamCacheManager;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanDescribeCapabilities;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Core\SensitiveDataRedactor;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceRequest;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceResponse;
use Cognesy\Polyglot\Inference\Data\DriverCapabilities;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;
use Cognesy\Polyglot\Inference\Errors\ProviderErrorClassifier;
use Cognesy\Polyglot\Inference\Events\InferenceFailed;
use Cognesy\Polyglot\Inference\Events\InferenceRequested;
use Cognesy\Polyglot\Inference\Events\InferenceResponseCreated;
use Cognesy\Polyglot\Inference\Streaming\EventStreamReader;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use Throwable;

abstract class BaseInferenceRequestDriver implements CanProcessInferenceRequest, CanDescribeCapabilities
{
    public function __construct(
        protected LLMConfig $config,
        protected CanSendHttpRequests $httpClient,
        protected EventDispatcherInterface $events,
        protected CanTranslateInferenceRequest $requestTranslator,
        protected CanTranslateInferenceResponse $responseTranslator,
        protected ?CanManageStreamCache $streamCacheManager = null,
    ) {}

    #[\Override]
    public function makeResponseFor(InferenceRequest $request): InferenceResponse {
        $httpRequest = $this->toHttpRequest($request);
        $httpResponse = $this->makeHttpResponse($httpRequest);
        return $this->httpResponseToInference($httpResponse, $request);
    }

    /** @return iterable<PartialInferenceDelta> */
    #[\Override]
    public function makeStreamDeltasFor(InferenceRequest $request): iterable {
        $httpRequest = $this->toHttpRequest($request);
        $httpResponse = $this->makeHttpResponse($httpRequest);
        $cachePolicy = $this->toStreamCachePolicy($request->responseCachePolicy());
        $httpResponse = $this->streamCacheManager()->manage($httpResponse, $cachePolicy);
        return $this->httpResponseToInferenceDeltas($httpResponse);
    }

    public function withStreamCacheManager(?CanManageStreamCache $streamCacheManager): static {
        $copy = clone $this;
        $copy->streamCacheManager = $streamCacheManager;
        return $copy;
    }

    /**
     * Get driver capabilities, optionally for a specific model.
     *
     * Default implementation returns full capabilities.
     * Override in subclasses for providers with restrictions.
     */
    public function capabilities(?string $model = null): DriverCapabilities {
        return new DriverCapabilities();
    }

    // INTERNAL //////////////////////////////////////////////

    protected function toHttpRequest(InferenceRequest $request): HttpRequest {
        $this->events->dispatch(new InferenceRequested($this->requestEventData($request)));
        return $this->requestTranslator->toHttpRequest($request);
    }

    protected function httpResponseToInference(HttpResponse $httpResponse, InferenceRequest $request): InferenceResponse {
        try {
            $inferenceResponse = $this->responseTranslator->fromResponse($httpResponse);
            if ($inferenceResponse === null) {
                throw new RuntimeException('Failed to translate HTTP response to InferenceResponse');
            }
        } catch (Throwable $e) {
            $this->dispatchInferenceProcessingFailed($httpResponse, $e);
            throw $e;
        }

        $this->events->dispatch(new InferenceResponseCreated($this->responseEventData($inferenceResponse, $request)));
        return $inferenceResponse;
    }

    /**
     * @return iterable<PartialInferenceDelta>
     */
    protected function httpResponseToInferenceDeltas(HttpResponse $httpResponse): iterable {
        $reader = new EventStreamReader(
            events: $this->events,
            parser: $this->toEventBody(...),
        );

        try {
            yield from $this->responseTranslator->fromStreamDeltas(
                $reader->eventsFrom($httpResponse->stream()),
                $httpResponse,
            );
        } catch (Throwable $e) {
            $this->dispatchInferenceStreamFailed($httpResponse, $e);
            throw $e;
        }
    }

    protected function makeHttpResponse(HttpRequest $request): HttpResponse {
        try {
            $httpResponse = $this->httpClient->send($request)->get();
        } catch (HttpRequestException $e) {
            $this->dispatchInferenceSendingFailed($request, $e);
            throw ProviderErrorClassifier::fromHttpException($e);
        } catch (Throwable $e) {
            $this->dispatchInferenceSendingFailed($request, $e);
            throw $e;
        }

        if ($httpResponse->statusCode() >= 400) {
            $errorResponse = $this->bufferStreamedErrorResponse($httpResponse);
            $this->dispatchInferenceResponseFailed($errorResponse);
            throw ProviderErrorClassifier::fromHttpResponse($errorResponse);
        }
        return $httpResponse;
    }

    protected function toEventBody(string $data): string|bool {
        return $this->responseTranslator->toEventBody($data);
    }

    private function streamCacheManager(): CanManageStreamCache {
        return $this->streamCacheManager ?? new StreamCacheManager();
    }

    private function toStreamCachePolicy(ResponseCachePolicy $policy): StreamCachePolicy {
        return match ($policy) {
            ResponseCachePolicy::None => StreamCachePolicy::None,
            ResponseCachePolicy::Memory => StreamCachePolicy::Memory,
        };
    }

    // EVENTS //////////////////////////////////////////////////

    private function dispatchInferenceResponseFailed(HttpResponse $httpResponse): void {
        $this->events->dispatch(new InferenceFailed([
            'context' => 'HTTP response received with error status',
            'statusCode' => $httpResponse->statusCode(),
            'headers' => $this->redactedHeaders($httpResponse->headers()),
            'body' => $this->redactedBody($httpResponse),
        ]));
    }

    private function dispatchInferenceSendingFailed(HttpRequest $request, Throwable $source): void {
        $this->events->dispatch(new InferenceFailed([
            'context' => 'HTTP request sending failed',
            'exception' => $this->redactedExceptionMessage($source, $request),
            'request' => $this->redactedRequest($request),
        ]));
    }

    private function dispatchInferenceStreamFailed(HttpResponse $response, Throwable $e): void {
        $this->events->dispatch(new InferenceFailed([
            'context' => 'Failed to process streamed response',
            'exception' => $e->getMessage(),
            'statusCode' => $response->statusCode(),
            'headers' => $this->redactedHeaders($response->headers()),
            'body' => $this->redactedBody($response),
        ]));
    }

    private function dispatchInferenceProcessingFailed(HttpResponse $httpResponse, Throwable $e): void {
        $this->events->dispatch(new InferenceFailed([
            'context' => 'Failed to process response',
            'exception' => $e->getMessage(),
            'statusCode' => $httpResponse->statusCode(),
            'headers' => $this->redactedHeaders($httpResponse->headers()),
            'body' => $this->redactedBody($httpResponse),
        ]));
    }

    private function redactedBody(HttpResponse $response): string {
        if (!$response->isStreamed()) {
            return '[REDACTED]';
        }
        return '[streamed response body redacted]';
    }

    private function bufferStreamedErrorResponse(HttpResponse $response): HttpResponse {
        if (!$response->isStreamed()) {
            return $response;
        }

        $body = '';
        foreach ($response->stream() as $chunk) {
            $body .= $chunk;
        }

        return HttpResponse::sync(
            statusCode: $response->statusCode(),
            headers: $response->headers(),
            body: $body,
        );
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
     * @return array<string,mixed>
     */
    private function requestEventData(InferenceRequest $request): array {
        $cachedContext = $request->cachedContext();

        return [
            'requestId' => $request->id()->toString(),
            'model' => $request->model(),
            'isStreamed' => $request->isStreamed(),
            'messageCount' => count($request->messages()),
            'toolCount' => $request->tools()->count(),
            'hasTools' => $request->hasTools(),
            'hasToolChoice' => $request->hasToolChoice(),
            'hasResponseFormat' => $request->hasResponseFormat(),
            'hasOptions' => $request->hasOptions(),
            'optionKeys' => array_keys($request->options()),
            'responseCachePolicy' => $request->responseCachePolicy()->value,
            'hasRetryPolicy' => $request->retryPolicy() !== null,
            'hasTelemetryCorrelation' => $request->telemetryCorrelation() !== null,
            'hasCachedContext' => $cachedContext !== null && !$cachedContext->isEmpty(),
            'cachedMessageCount' => $cachedContext?->messages()->count() ?? 0,
            'cachedToolCount' => $cachedContext?->tools()->count() ?? 0,
            'hasCachedToolChoice' => $cachedContext !== null && !$cachedContext->toolChoice()->isEmpty(),
            'hasCachedResponseFormat' => $cachedContext !== null && !$cachedContext->responseFormat()->isEmpty(),
        ];
    }

    private function responseEventData(InferenceResponse $response, InferenceRequest $request): array
    {
        $payload = [
            'requestId' => $request->id()->toString(),
            'model' => $request->model(),
            'responseId' => $response->id->toString(),
            'finishReason' => $response->finishReason()->value,
            'contentLength' => strlen($response->content()),
            'reasoningContentLength' => strlen($response->reasoningContent()),
            'hasToolCalls' => $response->hasToolCalls(),
            'toolCallCount' => $response->toolCalls()->count(),
            'usage' => $response->usage()->toArray(),
            'isPartial' => $response->isPartial(),
        ];

        $statusCode = $response->responseData()->statusCode();
        if ($statusCode > 0) {
            $payload['statusCode'] = $statusCode;
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
        return SensitiveDataRedactor::redactValues($data);
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
        return SensitiveDataRedactor::isSensitiveKey($key);
    }
}
