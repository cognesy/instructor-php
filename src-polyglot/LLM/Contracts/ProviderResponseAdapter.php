<?php

namespace Cognesy\Polyglot\LLM\Contracts;

use Cognesy\Polyglot\LLM\Data\LLMResponse;
use Cognesy\Polyglot\LLM\Data\PartialLLMResponse;

interface ProviderResponseAdapter
{
    public function fromResponse(array $data): ?LLMResponse;
    public function fromStreamResponse(array $data): ?PartialLLMResponse;
    public function fromStreamData(string $data): string|bool;
}