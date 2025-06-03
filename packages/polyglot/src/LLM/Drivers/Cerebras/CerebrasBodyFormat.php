<?php

namespace Cognesy\Polyglot\LLM\Drivers\Cerebras;

use Cognesy\Polyglot\LLM\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\LLM\InferenceRequest;

class CerebrasBodyFormat extends OpenAICompatibleBodyFormat
{
    public function toRequestBody(InferenceRequest $request) : array {
        $requestBody = parent::toRequestBody($request);

        $requestBody['max_completion_tokens'] = $requestBody['max_tokens'] ?? -1;
        unset($requestBody['max_tokens']);

        return $requestBody;
    }

    protected function supportsNonTextResponseForTools(InferenceRequest $request) : bool {
        return false;
    }
}