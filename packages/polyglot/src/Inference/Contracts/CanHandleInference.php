<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Contracts;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;

interface CanHandleInference
{
    public function makeResponseFor(InferenceRequest $request) : InferenceResponse;

    /** iterable<PartialInferenceResponse> */
    public function makeStreamResponsesFor(InferenceRequest $request): iterable;

    // direct access to HTTP request/response conversion methods

    public function toHttpRequest(InferenceRequest $request): HttpRequest;
    public function httpResponseToInference(HttpResponse $httpResponse): InferenceResponse;

    /** iterable<PartialInferenceResponse> */
    public function httpResponseToInferenceStream(HttpResponse $httpResponse): iterable;
}