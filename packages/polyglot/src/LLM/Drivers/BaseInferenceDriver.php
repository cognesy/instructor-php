<?php

namespace Cognesy\Polyglot\LLM\Drivers;

use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\LLM\Config\LLMConfig;
use Cognesy\Polyglot\LLM\Contracts\CanHandleInference;
use Cognesy\Polyglot\LLM\Contracts\ProviderRequestAdapter;
use Cognesy\Polyglot\LLM\Contracts\ProviderResponseAdapter;
use Cognesy\Polyglot\LLM\Data\InferenceResponse;
use Cognesy\Polyglot\LLM\Data\PartialInferenceResponse;
use Cognesy\Polyglot\LLM\InferenceRequest;
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
        return $this->httpClient->handle($clientRequest);
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