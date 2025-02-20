<?php

namespace Cognesy\LLM\LLM\Contracts;

use Cognesy\LLM\Http\Data\HttpClientRequest;
use Cognesy\LLM\LLM\Enums\Mode;

interface ProviderRequestAdapter
{
    public function toHttpClientRequest(
        array $messages,
        string $model,
        array $tools,
        string|array $toolChoice,
        array $responseFormat,
        array $options,
        Mode $mode,
    ) : HttpClientRequest;
}
