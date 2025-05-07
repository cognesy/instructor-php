<?php

namespace Cognesy\Polyglot\LLM\Drivers\Fireworks;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\LLM\Contracts\CanHandleInference;
use Cognesy\Polyglot\LLM\Contracts\ProviderRequestAdapter;
use Cognesy\Polyglot\LLM\Contracts\ProviderResponseAdapter;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\Data\LLMResponse;
use Cognesy\Polyglot\LLM\Data\PartialLLMResponse;
use Cognesy\Polyglot\LLM\Drivers\OpenAI\OpenAIMessageFormat;
use Cognesy\Polyglot\LLM\Drivers\OpenAI\OpenAIRequestAdapter;
use Cognesy\Polyglot\LLM\Drivers\OpenAI\OpenAIResponseAdapter;
use Cognesy\Polyglot\LLM\Drivers\OpenAI\OpenAIUsageFormat;
use Cognesy\Polyglot\LLM\InferenceRequest;
use Cognesy\Utils\Events\EventDispatcher;

class FireworksDriver implements CanHandleInference
{
    protected ProviderRequestAdapter $requestAdapter;
    protected ProviderResponseAdapter $responseAdapter;

    public function __construct(
        protected LLMConfig             $config,
        protected ?CanHandleHttpRequest $httpClient = null,
        protected ?EventDispatcher      $events = null,
    )
    {
        $this->events = $events ?? new EventDispatcher();
        $this->httpClient = $httpClient ?? HttpClient::make(events: $this->events);
        $this->requestAdapter = new OpenAIRequestAdapter(
            $config,
            new FireworksBodyFormat(
                $config,
                new OpenAIMessageFormat(),
            )
        );
        $this->responseAdapter = new OpenAIResponseAdapter(
            new OpenAIUsageFormat()
        );
    }

    public function handle(InferenceRequest $request): HttpClientResponse
    {
        $request = $request->withCacheApplied();
        $clientRequest = $this->requestAdapter->toHttpClientRequest(
            $request->messages(),
            $request->model(),
            $request->tools(),
            $request->toolChoice(),
            $request->responseFormat(),
            $request->options(),
            $request->mode(),
        );
        return $this->httpClient->handle(
            (new HttpClientRequest(
                url: $clientRequest->url(),
                method: $clientRequest->method(),
                headers: $clientRequest->headers(),
                body: $clientRequest->body()->toArray(),
                options: $clientRequest->options(),
            ))->withStreaming($clientRequest->isStreamed())
        );
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