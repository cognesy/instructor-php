<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Contracts;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;

interface CanHandleInference
{
    public function makeResponseFor(InferenceRequest $request) : InferenceResponse;
    /** @return iterable<PartialInferenceResponse> */
    public function makeStreamResponsesFor(InferenceRequest $request): iterable;

    public function toHttpRequest(InferenceRequest $request): HttpRequest;
    public function httpResponseToInference(HttpResponse $httpResponse): InferenceResponse;

    /** @return iterable<PartialInferenceResponse> */
    public function httpResponseToInferenceStream(HttpResponse $httpResponse): iterable;
}