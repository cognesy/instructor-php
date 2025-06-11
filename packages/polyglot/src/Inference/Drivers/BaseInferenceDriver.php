<?php

namespace Cognesy\Polyglot\Inference\Drivers;

use Cognesy\Http\Contracts\HttpClientResponse;
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

    public function handle(InferenceRequest $request): HttpClientResponse {
        $clientRequest = $this->requestAdapter->toHttpClientRequest($request);
        return $this->httpClient->withRequest($clientRequest)->get();
    }

    public function fromResponse(array $data): ?InferenceResponse {
        return $this->responseAdapter->fromResponse($data);
    }

    public function fromStreamResponse(array $data): ?PartialInferenceResponse {
        return $this->responseAdapter->fromStreamResponse($data);
    }

    public function fromStreamData(string $data): string|bool {
        return $this->responseAdapter->fromStreamData($data);
    }
}