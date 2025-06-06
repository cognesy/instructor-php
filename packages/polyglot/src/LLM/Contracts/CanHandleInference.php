<?php

namespace Cognesy\Polyglot\LLM\Contracts;

use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Polyglot\LLM\Data\LLMResponse;
use Cognesy\Polyglot\LLM\Data\PartialLLMResponse;
use Cognesy\Polyglot\LLM\InferenceRequest;

interface CanHandleInference
{
    /**
     * Handles an inference request and returns a response.
     *
     * @param InferenceRequest $request The inference request to handle.
     * @return HttpClientResponse The response from the HTTP client.
     */
    public function handle(InferenceRequest $request) : HttpClientResponse;

    /**
     * Converts the response data into an LLMResponse object.
     *
     * @param array $data The response data to convert.
     * @return LLMResponse|null The converted LLMResponse object or null if conversion fails.
     */
    public function fromResponse(array $data): ?LLMResponse;

    /**
     * Converts a stream response into a PartialLLMResponse object.
     *
     * @param array $data The stream response data to convert.
     * @return PartialLLMResponse|null The converted PartialLLMResponse object or null if conversion fails.
     */
    public function fromStreamResponse(array $data) : ?PartialLLMResponse;

    /**
     * Converts a string of stream data into a string or false if there's no more data.
     *
     * @param string $data The stream data to convert.
     * @return string|bool The converted data, or false if no more data is available.
     */
    public function fromStreamData(string $data): string|bool;
}