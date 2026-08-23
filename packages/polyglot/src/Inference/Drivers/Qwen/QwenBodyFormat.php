<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Qwen;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;

class QwenBodyFormat extends OpenAICompatibleBodyFormat
{
    #[\Override]
    public function toRequestBody(InferenceRequest $request) : array {
        $requestBody = parent::toRequestBody($request);
        if ($this->shouldMapThinkingToEnableThinking($requestBody)) {
            $requestBody['enable_thinking'] = $this->toBoolean($requestBody['thinking']);
        }
        unset($requestBody['thinking']);

        return $requestBody;
    }

    #[\Override]
    protected function supportsNonTextResponseForTools(InferenceRequest $request) : bool {
        return false;
    }

    /**
     * @param array<string,mixed> $requestBody
     */
    private function shouldMapThinkingToEnableThinking(array $requestBody): bool {
        if (array_key_exists('enable_thinking', $requestBody)) {
            return false;
        }

        return array_key_exists('thinking', $requestBody);
    }
}
