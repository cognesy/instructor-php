<?php

namespace Cognesy\Polyglot\LLM\Drivers\Cerebras;

use Cognesy\Polyglot\LLM\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\LLM\InferenceRequest;

class CerebrasBodyFormat extends OpenAICompatibleBodyFormat
{
    public function toRequestBody(InferenceRequest $request) : array {
        $requestData = parent::toRequestBody($request);

        $requestData['max_completion_tokens'] = $requestData['max_tokens'] ?? -1;
        unset($requestData['max_tokens']);

        return $requestData;
    }
}