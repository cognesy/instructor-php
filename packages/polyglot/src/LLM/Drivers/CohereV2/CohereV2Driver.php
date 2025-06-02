<?php

namespace Cognesy\Polyglot\LLM\Drivers\CohereV2;

use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\LLM\Contracts\CanHandleInference;
use Cognesy\Polyglot\LLM\Contracts\ProviderRequestAdapter;
use Cognesy\Polyglot\LLM\Contracts\ProviderResponseAdapter;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\Data\LLMResponse;
use Cognesy\Polyglot\LLM\Data\PartialLLMResponse;
use Cognesy\Polyglot\LLM\Drivers\OpenAI\OpenAIMessageFormat;
use Cognesy\Polyglot\LLM\InferenceRequest;
use Psr\EventDispatcher\EventDispatcherInterface;

class CohereV2Driver implements CanHandleInference
{
    protected ProviderRequestAdapter $requestAdapter;
    protected ProviderResponseAdapter $responseAdapter;

    public function __construct(
        protected LLMConfig $config,
        protected HttpClient $httpClient,
        protected EventDispatcherInterface $events,
    )
    {
        $this->requestAdapter = new CohereV2RequestAdapter(
            $config,
            new CohereV2BodyFormat(
                $config,
                new OpenAIMessageFormat(),
            )
        );
        $this->responseAdapter = new CohereV2ResponseAdapter(
            new CohereV2UsageFormat()
        );
    }

    public function handle(InferenceRequest $request): HttpClientResponse
    {
        $clientRequest = $this->requestAdapter->toHttpClientRequest(
            $request->withCacheApplied()
        );
        return $this->httpClient->handle($clientRequest);
    }

    public function fromResponse(array $data): ?LLMResponse
    {
        return $this->responseAdapter->fromResponse($data);
    }

    public function fromStreamResponse(array $data): ?PartialLLMResponse
    {
        return $this->responseAdapter->fromStreamResponse($data);
    }

    public function fromStreamData(string $data): string|bool
    {
        return $this->responseAdapter->fromStreamData($data);
    }
}