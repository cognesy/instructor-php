<?php

namespace Cognesy\Instructor\Features\LLM\Drivers;

use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Features\Http\Contracts\ResponseAdapter;
use Cognesy\Instructor\Features\Http\Contracts\CanHandleHttp;
use Cognesy\Instructor\Features\Http\Data\HttpClientRequest;
use Cognesy\Instructor\Features\Http\HttpClient;
use Cognesy\Instructor\Features\LLM\Contracts\CanHandleInference;
use Cognesy\Instructor\Features\LLM\Contracts\ProviderRequestAdapter;
use Cognesy\Instructor\Features\LLM\Contracts\ProviderResponseAdapter;
use Cognesy\Instructor\Features\LLM\Data\LLMConfig;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
use Cognesy\Instructor\Features\LLM\Data\PartialLLMResponse;
use Cognesy\Instructor\Features\LLM\InferenceRequest;

/**
 * ModularLLMDriver is responsible for handling inference requests and managing
 * the interaction between request/response adapters and an HTTP client for
 * communication with a large language model (LLM) backend.
 *
 * This class implements CanHandleInference interface, providing methods
 * to handle inference requests, convert responses from the LLM backend,
 * and manage streaming responses where applicable.
 */
class ModularLLMDriver implements CanHandleInference {
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

    /**
     * Processes the given inference request and handles it through the HTTP client.
     *
     * @param InferenceRequest $request The request to be processed, including messages, model, tools, and other parameters.
     * @return ResponseAdapter The response indicating the access result after processing the request.
     */
    public function handle(InferenceRequest $request): ResponseAdapter {
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
                body: $clientRequest->body(),
                options: $clientRequest->options(),
            ))->withStreaming($clientRequest->isStreamed())
        );
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