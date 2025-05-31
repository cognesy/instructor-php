<?php

namespace Cognesy\Polyglot\LLM\Drivers;

use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\LLM\Contracts\CanHandleInference;
use Cognesy\Polyglot\LLM\Contracts\ProviderRequestAdapter;
use Cognesy\Polyglot\LLM\Contracts\ProviderResponseAdapter;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\Data\LLMResponse;
use Cognesy\Polyglot\LLM\Data\PartialLLMResponse;
use Cognesy\Polyglot\LLM\InferenceRequest;
use JetBrains\PhpStorm\Deprecated;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * ModularLLMDriver is responsible for handling inference requests and managing
 * the interaction between request/response adapters and an HTTP client for
 * communication with a large language model (LLM) backend.
 *
 * This class implements CanHandleInference interface, providing methods
 * to handle inference requests, convert responses from the LLM backend,
 * and manage streaming responses where applicable.
 */
#[Deprecated('Use individual drivers instead')]
class ModularLLMDriver implements CanHandleInference {
    public function __construct(
        protected LLMConfig               $config,
        protected ProviderRequestAdapter  $requestAdapter,
        protected ProviderResponseAdapter $responseAdapter,
        protected HttpClient $httpClient,
        protected EventDispatcherInterface $events,
    ) {}

    /**
     * Processes the given inference request and handles it through the HTTP client.
     *
     * @param InferenceRequest $request The request to be processed, including messages, model, tools, and other parameters.
     * @return HttpClientResponse The response indicating the access result after processing the request.
     */
    public function handle(InferenceRequest $request): HttpClientResponse {
        $request = $request->withCacheApplied();
        $clientRequest = $this->requestAdapter->toHttpClientRequest(
            $request->messages(),
            $request->model(),
            $request->tools(),
            $request->toolChoice(),
            $request->responseFormat(),
            $request->options(),
            $request->outputMode(),
        );
        return $this->httpClient->handle($clientRequest);
    }

    /**
     * Converts response data (array decoded from JSON)
     * into an LLMResponse object using the response adapter.
     *
     * @param array $data The response data to be converted.
     * @return LLMResponse|null The converted LLMResponse object or null if conversion fails.
     */
    public function fromResponse(array $data): ?LLMResponse {
        return $this->responseAdapter->fromResponse($data);
    }

    /**
     * Processes stream response data (array decoded from JSON)
     * and converts it into a PartialLLMResponse object.
     *
     * @param array $data An array containing the stream response data to process.
     * @return PartialLLMResponse|null The converted PartialLLMResponse object, or null if the conversion is not possible.
     */
    public function fromStreamResponse(array $data): ?PartialLLMResponse {
        return $this->responseAdapter->fromStreamResponse($data);
    }

    /**
     * Processes raw stream data and converts it to data string
     * using the response adapter.
     *
     * False is returned if there's no data to process.
     *
     * @param string $data A string containing the stream data to process.
     * @return string|bool The processed data as a string, or false if there's no data to process.
     */
    public function fromStreamData(string $data): string|bool {
        return $this->responseAdapter->fromStreamData($data);
    }
}