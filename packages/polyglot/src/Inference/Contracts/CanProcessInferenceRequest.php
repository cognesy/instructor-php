<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Contracts;

use Cognesy\Polyglot\Inference\Data\DriverCapabilities;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;

interface CanProcessInferenceRequest
{
    public function makeResponseFor(InferenceRequest $request) : InferenceResponse;

    /** @return iterable<PartialInferenceResponse> */
    public function makeStreamResponsesFor(InferenceRequest $request): iterable;

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