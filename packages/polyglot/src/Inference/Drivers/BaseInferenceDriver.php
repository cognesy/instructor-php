<?php

namespace Cognesy\Polyglot\Inference\Drivers;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanHandleInference;
use Cognesy\Polyglot\Inference\Contracts\ProviderRequestAdapter;
use Cognesy\Polyglot\Inference\Contracts\ProviderResponseAdapter;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Events\InferenceFailed;
use Cognesy\Polyglot\Inference\Events\InferenceRequested;
use Cognesy\Polyglot\Inference\Events\InferenceResponseCreated;
use Cognesy\Polyglot\Inference\Utils\EventStreamReader;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;

abstract class BaseInferenceDriver implements CanHandleInference
{
    protected LLMConfig $config;
    protected HttpClient $httpClient;
    protected EventDispatcherInterface $events;

    protected ProviderRequestAdapter $requestAdapter;
    protected ProviderResponseAdapter $responseAdapter;

    // HIGH LEVEL API - ADDED DURING UNFINISHED REFACTORING

    public function makeResponseFor(InferenceRequest $request) : InferenceResponse {
        $httpResponse = $this->handle($request);
        try {
            $inferenceResponse = $this->fromResponse($httpResponse);
        } catch (Exception $e) {
            $this->events->dispatch(new InferenceFailed([
                'exception' => $e->getMessage(),
                'statusCode' => $httpResponse->statusCode() ?? 500,
                'headers' => $httpResponse->headers() ?? [],
                'body' => $httpResponse->body() ?? '',
            ]));
            throw $e;
        }
        $this->events->dispatch(new InferenceResponseCreated(['response' => $inferenceResponse?->toArray() ?? []]));
        return $inferenceResponse;
    }

    /** iterable<PartialInferenceResponse> */
    public function makeStreamResponsesFor(InferenceRequest $request): iterable {
        $httpResponse = $this->handle($request);
        $reader = new EventStreamReader(
            parser: $this->toEventBody(...),
            events: $this->events,
        );
        foreach($reader->eventsFrom($httpResponse->stream()) as $eventBody) {
            $partialResponse = $this->fromStreamResponse($eventBody);
            if ($partialResponse === null) {
                continue;
            }
            yield $partialResponse;
        }
    }

    // LOW LEVEL API - PRE-REFACTORING API, SIGNATURES MODIFIED DURING REFACTORING

    public function handle(InferenceRequest $request): HttpResponse {
        $this->events->dispatch(new InferenceRequested(['request' => $request->toArray()]));
        $httpRequest = $this->makeHttpRequest($request);
        $httpResponse = $this->httpClient
            ->withRequest($httpRequest)
            ->get();
        return $httpResponse;
    }

    public function makeHttpRequest(InferenceRequest $request): HttpRequest {
        return $this->requestAdapter->toHttpRequest($request);
    }

    public function fromResponse(HttpResponse $response): ?InferenceResponse {
        return $this->responseAdapter->fromResponse($response);
    }

    public function fromStreamResponse(string $eventBody): ?PartialInferenceResponse {
        return $this->responseAdapter->fromStreamResponse($eventBody);
    }

    public function toEventBody(string $data): string|bool {
        return $this->responseAdapter->toEventBody($data);
    }
}