<?php

namespace Cognesy\Instructor\Features\LLM\Contracts;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Features\Http\Data\HttpClientRequest;

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
