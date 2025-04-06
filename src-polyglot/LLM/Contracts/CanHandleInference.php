<?php

namespace Cognesy\Polyglot\LLM\Contracts;

use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Polyglot\LLM\Data\LLMResponse;
use Cognesy\Polyglot\LLM\Data\PartialLLMResponse;
use Cognesy\Polyglot\LLM\InferenceRequest;

interface CanHandleInference
{
    public function handle(InferenceRequest $request) : HttpClientResponse;
    public function fromResponse(array $data): ?LLMResponse;
    public function fromStreamResponse(array $data) : ?PartialLLMResponse;
    public function fromStreamData(string $data): string|bool;
}