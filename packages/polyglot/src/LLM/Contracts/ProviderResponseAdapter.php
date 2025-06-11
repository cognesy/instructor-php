<?php

namespace Cognesy\Polyglot\LLM\Contracts;

use Cognesy\Polyglot\LLM\Data\InferenceResponse;
use Cognesy\Polyglot\LLM\Data\PartialInferenceResponse;

interface ProviderResponseAdapter
{
    public function fromResponse(array $data): ?InferenceResponse;
    public function fromStreamResponse(array $data): ?PartialInferenceResponse;
    public function fromStreamData(string $data): string|bool;
}