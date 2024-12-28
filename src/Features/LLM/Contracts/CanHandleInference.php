<?php

namespace Cognesy\Instructor\Features\LLM\Contracts;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Features\Http\Contracts\CanAccessResponse;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
use Cognesy\Instructor\Features\LLM\Data\PartialLLMResponse;
use Cognesy\Instructor\Features\LLM\InferenceRequest;

interface CanHandleInference
{
    public function handle(InferenceRequest $request) : CanAccessResponse;
    public function getEndpointUrl(InferenceRequest $request) : string;
    public function getRequestHeaders() : array;
    public function getRequestBody(
        array $messages = [],
        string $model = '',
        array $tools = [],
        string|array $toolChoice = '',
        array $responseFormat = [],
        array $options = [],
        Mode $mode = Mode::Text,
    ) : array;
    public function toLLMResponse(array $data): ?LLMResponse;
    public function toPartialLLMResponse(array $data) : ?PartialLLMResponse;
    public function getStreamData(string $data): string|bool;
}