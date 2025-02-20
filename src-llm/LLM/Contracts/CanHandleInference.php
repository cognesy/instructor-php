<?php

namespace Cognesy\LLM\LLM\Contracts;

use Cognesy\LLM\Http\Contracts\ResponseAdapter;
use Cognesy\LLM\LLM\Data\LLMResponse;
use Cognesy\LLM\LLM\Data\PartialLLMResponse;
use Cognesy\LLM\LLM\InferenceRequest;

interface CanHandleInference
{
    public function handle(InferenceRequest $request) : ResponseAdapter;
    public function fromResponse(array $data): ?LLMResponse;
    public function fromStreamResponse(array $data) : ?PartialLLMResponse;
    public function fromStreamData(string $data): string|bool;
}