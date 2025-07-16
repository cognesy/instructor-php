<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Cerebras;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;

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