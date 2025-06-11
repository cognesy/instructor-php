<?php

namespace Cognesy\Polyglot\Inference\Contracts;

use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;

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
     * Converts the response data into an InferenceResponse object.
     *
     * @param array $data The response data to convert.
     * @return InferenceResponse|null The converted InferenceResponse object or null if conversion fails.
     */
    public function fromResponse(array $data): ?InferenceResponse;

    /**
     * Converts a stream response into a PartialInferenceResponse object.
     *
     * @param array $data The stream response data to convert.
     * @return PartialInferenceResponse|null The converted PartialInferenceResponse object or null if conversion fails.
     */
    public function fromStreamResponse(array $data) : ?PartialInferenceResponse;

    /**
     * Converts a string of stream data into a string or false if there's no more data.
     *
     * @param string $data The stream data to convert.
     * @return string|bool The converted data, or false if no more data is available.
     */
    public function fromStreamData(string $data): string|bool;
}