<?php

namespace Cognesy\Polyglot\LLM\Contracts;

use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Polyglot\LLM\Enums\Mode;

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
