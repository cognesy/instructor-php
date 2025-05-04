<?php

namespace Cognesy\Polyglot\LLM\Contracts;

use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Polyglot\LLM\Enums\OutputMode;

interface ProviderRequestAdapter
{
    public function toHttpClientRequest(
        array        $messages,
        string       $model,
        array        $tools,
        string|array $toolChoice,
        array        $responseFormat,
        array        $options,
        OutputMode   $mode,
    ) : HttpClientRequest;
}
