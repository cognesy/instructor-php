<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Contracts;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Inference\Data\DriverCapabilities;
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

    /**
     * Get driver capabilities, optionally for a specific model.
     *
     * The model parameter allows model-specific capability checks, e.g.,
     * deepseek-reasoner has different capabilities than deepseek-chat.
     *
     * If model is null, capabilities for the configured default model are returned.
     */
    public function capabilities(?string $model = null): DriverCapabilities;
}