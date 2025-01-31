<?php

namespace Cognesy\Instructor\Features\LLM\Contracts;

use Cognesy\Instructor\Features\Http\Contracts\ResponseAdapter;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
use Cognesy\Instructor\Features\LLM\Data\PartialLLMResponse;
use Cognesy\Instructor\Features\LLM\InferenceRequest;

interface CanHandleInference
{
    public function handle(InferenceRequest $request) : ResponseAdapter;
    public function fromResponse(array $data): ?LLMResponse;
    public function fromStreamResponse(array $data) : ?PartialLLMResponse;
    public function fromStreamData(string $data): string|bool;
}