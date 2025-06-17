<?php

namespace Cognesy\Polyglot\Inference\Drivers;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\HttpClient;
use Cognesy\Http\PendingHttpResponse;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanHandleInference;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceRequest;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceResponse;
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

    protected CanTranslateInferenceRequest $requestTranslator;
    protected CanTranslateInferenceResponse $responseTranslator;

    public function makeResponseFor(InferenceRequest $request) : InferenceResponse {
        $httpResponse = $this->makeHttpResponse($request);

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
        $httpResponse = $this->makeHttpResponse($request);

        try {
            $reader = new EventStreamReader(
                events: $this->events,
                parser: $this->toEventBody(...),
            );
            foreach($reader->eventsFrom($httpResponse->stream()) as $eventBody) {
                $partialResponse = $this->fromStreamResponse($eventBody);
                if ($partialResponse === null) {
                    continue;
                }
                yield $partialResponse;
            }
        } catch (Exception $e) {
            $this->events->dispatch(new InferenceFailed([
                'exception' => $e->getMessage(),
                'statusCode' => $httpResponse->statusCode() ?? 500,
                'headers' => $httpResponse->headers() ?? [],
                'body' => $httpResponse->body() ?? '',
            ]));
            throw $e;
        }
    }

    // INTERNAL //////////////////////////////////////////////

    protected function makeHttpResponse(InferenceRequest $request): HttpResponse {
        $this->events->dispatch(new InferenceRequested(['request' => $request->toArray()]));

        try {
            $httpRequest = $this->makeHttpRequest($request);
            $pendingHttpResponse = $this->handleHttpRequest($httpRequest);
            $httpResponse = $pendingHttpResponse->get();
        } catch(Exception $e) {
            $this->events->dispatch(new InferenceFailed([
                'exception' => $e->getMessage(),
                'request' => $request->toArray(),
            ]));
            throw $e;
        }

        return $httpResponse;
    }

    protected function handleHttpRequest(HttpRequest $request): PendingHttpResponse {
        return $this->httpClient->withRequest($request);
    }

    protected function makeHttpRequest(InferenceRequest $request): HttpRequest {
        return $this->requestTranslator->toHttpRequest($request);
    }

    protected function fromResponse(HttpResponse $response): ?InferenceResponse {
        return $this->responseTranslator->fromResponse($response);
    }

    protected function fromStreamResponse(string $eventBody): ?PartialInferenceResponse {
        return $this->responseTranslator->fromStreamResponse($eventBody);
    }

    protected function toEventBody(string $data): string|bool {
        return $this->responseTranslator->toEventBody($data);
    }
}