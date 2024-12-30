<?php

namespace Cognesy\Instructor\Features\LLM\Drivers;

use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Features\Http\Contracts\CanAccessResponse;
use Cognesy\Instructor\Features\Http\Contracts\CanHandleHttp;
use Cognesy\Instructor\Features\Http\HttpClient;
use Cognesy\Instructor\Features\LLM\Contracts\CanHandleInference;
use Cognesy\Instructor\Features\LLM\Contracts\ProviderRequestAdapter;
use Cognesy\Instructor\Features\LLM\Contracts\ProviderResponseAdapter;
use Cognesy\Instructor\Features\LLM\Data\LLMConfig;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
use Cognesy\Instructor\Features\LLM\Data\PartialLLMResponse;
use Cognesy\Instructor\Features\LLM\InferenceRequest;

class DefaultDriver implements CanHandleInference {
    public function __construct(
        protected LLMConfig $config,
        protected ProviderRequestAdapter $requestAdapter,
        protected ProviderResponseAdapter $responseAdapter,
        protected ?CanHandleHttp $httpClient = null,
        protected ?EventDispatcher $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->httpClient = $httpClient ?? HttpClient::make(events: $this->events);
    }

    public function handle(InferenceRequest $request): CanAccessResponse {
        $request = $request->withCacheApplied();
        return $this->httpClient->handle(
            url: $this->requestAdapter->toUrl($request->model(), $request->isStreamed()),
            headers: $this->requestAdapter->toHeaders(),
            body: $this->requestAdapter->toRequestBody(
                $request->messages(),
                $request->model(),
                $request->tools(),
                $request->toolChoice(),
                $request->responseFormat(),
                $request->options(),
                $request->mode(),
            ),
            streaming: $request->isStreamed(),
        );
    }

    public function fromResponse(array $data): ?LLMResponse {
        return $this->responseAdapter->fromResponse($data);
    }

    public function fromStreamResponse(array $data): ?PartialLLMResponse {
        return $this->responseAdapter->fromStreamResponse($data);
    }

    public function fromStreamData(string $data): string|bool {
        return $this->responseAdapter->fromStreamData($data);
    }
}