<?php

namespace Cognesy\Polyglot\Inference\Contracts;

use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;

interface ProviderResponseAdapter
{
    public function fromResponse(array $data): ?InferenceResponse;
    public function fromStreamResponse(array $data): ?PartialInferenceResponse;
    public function fromStreamData(string $data): string|bool;
}