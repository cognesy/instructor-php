<?php

namespace Cognesy\Polyglot\LLM\Contracts;

use Cognesy\Polyglot\LLM\Enums\Mode;

interface CanMapRequestBody
{
    public function map(
        array $messages,
        string $model,
        array $tools,
        array|string $toolChoice,
        array $responseFormat,
        array $options,
        Mode $mode
    ): array;
}