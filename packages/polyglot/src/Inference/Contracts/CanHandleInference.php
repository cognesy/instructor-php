<?php

namespace Cognesy\Polyglot\Inference\Contracts;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;

interface CanHandleInference
{
    public function makeResponseFor(InferenceRequest $request) : InferenceResponse;

    /** iterable<PartialInferenceResponse> */
    public function makeStreamResponsesFor(InferenceRequest $request): iterable;

    /**
     * Handles an inference request and returns a response.
     *
     * @param InferenceRequest $request The inference request to handle.
     * @return HttpResponse The response from the HTTP client.
     */
    public function handle(InferenceRequest $request) : HttpResponse;

    /**
     * Converts the response data into an InferenceResponse object.
     *
     * @param array $data The response data to convert.
     * @return InferenceResponse|null The converted InferenceResponse object or null if conversion fails.
     */
    public function fromResponse(HttpResponse $response): ?InferenceResponse;

    /**
     * Converts a stream response into a PartialInferenceResponse object.
     *
     * @param array $data The stream response data to convert.
     * @return PartialInferenceResponse|null The converted PartialInferenceResponse object or null if conversion fails.
     */
    public function fromStreamResponse(string $eventBody) : ?PartialInferenceResponse;

    /**
     * Converts a string of stream data into event body string (e.g. removing 'data:' prefixes) or false if there's no more data.
     *
     * @param string $data The stream data to convert.
     * @return string|bool The event data content, or false if no more data is available.
     */
    public function toEventBody(string $data): string|bool;
}