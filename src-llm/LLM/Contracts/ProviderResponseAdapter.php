<?php

namespace Cognesy\LLM\LLM\Contracts;

use Cognesy\LLM\LLM\Data\LLMResponse;
use Cognesy\LLM\LLM\Data\PartialLLMResponse;

interface ProviderResponseAdapter
{
    public function fromResponse(array $data): ?LLMResponse;
    public function fromStreamResponse(array $data): ?PartialLLMResponse;
    public function fromStreamData(string $data): string|bool;
}