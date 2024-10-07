<?php

namespace Cognesy\Instructor\Extras\LLM\Contracts;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Http\Contracts\CanAccessResponse;
use Cognesy\Instructor\Extras\LLM\Data\LLMResponse;
use Cognesy\Instructor\Extras\LLM\Data\PartialLLMResponse;
use Cognesy\Instructor\Extras\LLM\InferenceRequest;

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
    public function getData(string $data): string|bool;
}