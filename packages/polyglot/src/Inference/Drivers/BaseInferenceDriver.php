<?php

namespace Cognesy\Polyglot\Inference\Drivers;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanHandleInference;
use Cognesy\Polyglot\Inference\Contracts\ProviderRequestAdapter;
use Cognesy\Polyglot\Inference\Contracts\ProviderResponseAdapter;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Psr\EventDispatcher\EventDispatcherInterface;

abstract class BaseInferenceDriver implements CanHandleInference
{
    protected LLMConfig $config;
    protected HttpClient $httpClient;
    protected EventDispatcherInterface $events;

    protected ProviderRequestAdapter $requestAdapter;
    protected ProviderResponseAdapter $responseAdapter;

    public function handle(InferenceRequest $request): HttpResponse {
        $clientRequest = $this->requestAdapter->toHttpClientRequest($request);
        return $this->httpClient->withRequest($clientRequest)->get();
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