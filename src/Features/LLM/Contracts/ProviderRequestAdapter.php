<?php

namespace Cognesy\Instructor\Features\LLM\Contracts;

use Cognesy\Instructor\Enums\Mode;

interface ProviderRequestAdapter
{
    public function toHeaders(): array;
    public function toUrl(string $model = '', bool $stream = false): string;
    public function toRequestBody(
        array $messages,
        string $model,
        array $tools,
        string|array $toolChoice,
        array $responseFormat,
        array $options,
        Mode $mode
    ): array;
}
