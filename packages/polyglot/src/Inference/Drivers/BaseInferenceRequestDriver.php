<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\HttpClient;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceRequest;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceResponse;
use Cognesy\Polyglot\Inference\Data\DriverCapabilities;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Errors\ProviderErrorClassifier;
use Cognesy\Polyglot\Inference\Events\InferenceFailed;
use Cognesy\Polyglot\Inference\Events\InferenceRequested;
use Cognesy\Polyglot\Inference\Events\InferenceResponseCreated;
use Cognesy\Polyglot\Inference\Streaming\EventStreamReader;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use Throwable;

abstract class BaseInferenceRequestDriver implements CanProcessInferenceRequest
{
    public function __construct(
        protected LLMConfig $config,
        protected HttpClient $httpClient,
        protected EventDispatcherInterface $events,
        protected CanTranslateInferenceRequest $requestTranslator,
        protected CanTranslateInferenceResponse $responseTranslator,
    ) {}

    #[\Override]
    public function makeResponseFor(InferenceRequest $request): InferenceResponse {
        $httpRequest = $this->toHttpRequest($request);
        $httpResponse = $this->makeHttpResponse($httpRequest);
        return $this->httpResponseToInference($httpResponse);
    }

    /**
     * @return iterable<PartialInferenceResponse>
     */
    #[\Override]
    public function makeStreamResponsesFor(InferenceRequest $request): iterable {
        $httpRequest = $this->toHttpRequest($request);
        $httpResponse = $this->makeHttpResponse($httpRequest);
        return $this->httpResponseToInferenceStream($httpResponse);
    }

    /**
     * Get driver capabilities, optionally for a specific model.
     *
     * Default implementation returns full capabilities.
     * Override in subclasses for providers with restrictions.
     */
    #[\Override]
    public function capabilities(?string $model = null): DriverCapabilities {
        return new DriverCapabilities(
            outputModes: OutputMode::cases(),
            streaming: true,
            toolCalling: true,
            jsonSchema: true,
            responseFormatWithTools: true,
        );
    }

    // INTERNAL //////////////////////////////////////////////

    protected function toHttpRequest(InferenceRequest $request): HttpRequest {
        $this->events->dispatch(new InferenceRequested(['request' => $request->toArray()]));
        return $this->requestTranslator->toHttpRequest($request);
    }

    protected function httpResponseToInference(HttpResponse $httpResponse): InferenceResponse {
        try {
            $inferenceResponse = $this->responseTranslator->fromResponse($httpResponse);
            if ($inferenceResponse === null) {
                throw new RuntimeException('Failed to translate HTTP response to InferenceResponse');
            }
        } catch (Throwable $e) {
            $this->dispatchInferenceProcessingFailed($httpResponse, $e);
            throw $e;
        }

        // Attach pricing from config if available
        $pricing = $this->config->getPricing();
        if ($pricing->hasAnyPricing()) {
            $inferenceResponse = $inferenceResponse->withPricing($pricing);
        }

        $this->events->dispatch(new InferenceResponseCreated(['response' => $inferenceResponse->toArray()]));
        return $inferenceResponse;
    }

    /**
     * @return iterable<PartialInferenceResponse>
     */
    protected function httpResponseToInferenceStream(HttpResponse $httpResponse): iterable {
        $reader = new EventStreamReader(
            events: $this->events,
            parser: $this->toEventBody(...),
        );
        try {
            yield from $this->responseTranslator->fromStreamResponses(
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
            $httpResponse = $this->httpClient->withRequest($request)->get();
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

    // EVENTS //////////////////////////////////////////////////

    private function dispatchInferenceResponseFailed(HttpResponse $httpResponse): void {
        $this->events->dispatch(new InferenceFailed([
            'context' => 'HTTP response received with error status',
            'statusCode' => $httpResponse->statusCode(),
            'headers' => $httpResponse->headers(),
            'body' => $this->safeBody($httpResponse),
        ]));
    }

    private function dispatchInferenceSendingFailed(HttpRequest $request, Throwable $source): void {
        $this->events->dispatch(new InferenceFailed([
            'context' => 'HTTP request sending failed',
            'exception' => $source->getMessage(),
            'request' => $request->toArray(),
        ]));
    }

    private function dispatchInferenceStreamFailed(HttpResponse $response, Throwable $e): void {
        $this->events->dispatch(new InferenceFailed([
            'context' => 'Failed to process streamed response',
            'exception' => $e->getMessage(),
            'statusCode' => $response->statusCode(),
            'headers' => $response->headers(),
            'body' => $this->safeBody($response),
        ]));
    }

    private function dispatchInferenceProcessingFailed(HttpResponse $httpResponse, Throwable $e): void {
        $this->events->dispatch(new InferenceFailed([
            'context' => 'Failed to process response',
            'exception' => $e->getMessage(),
            'statusCode' => $httpResponse->statusCode(),
            'headers' => $httpResponse->headers(),
            'body' => $this->safeBody($httpResponse),
        ]));
    }

    private function safeBody(HttpResponse $response): string {
        if (!$response->isStreamed()) {
            return $response->body();
        }
        return '[streamed response body unavailable]';
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
}
