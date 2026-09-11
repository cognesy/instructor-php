<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Qwen;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Override;

class QwenBodyFormat extends OpenAICompatibleBodyFormat
{
    #[Override]
    public function toRequestBody(InferenceRequest $request): array {
        $requestBody = parent::toRequestBody($request);
        if ($this->shouldMapThinkingToEnableThinking($requestBody)) {
            $requestBody['enable_thinking'] = $this->toBoolean($requestBody['thinking']);
        }
        unset($requestBody['thinking']);

        return $requestBody;
    }

    #[Override]
    protected function supportsNonTextResponseForTools(InferenceRequest $request): bool {
        return false;
    }

    #[Override]
    protected function supportsToolSelection(InferenceRequest $request): bool {
        if (!$request->toolChoice()->isSpecific()) {
            return true;
        }

        $options = array_merge($this->config->options, $request->options());
        $thinking = $options['enable_thinking'] ?? $options['thinking'] ?? false;

        // Qwen does not allow forcing a specific function while thinking mode is enabled.
        return !$this->toBoolean($thinking);
    }

    #[Override]
    protected function toToolChoice(InferenceRequest $request): array|string {
        // Qwen's OpenAI-compatible Chat API accepts auto, none, and a specific
        // function object, but not OpenAI's required mode. Treat required as
        // auto so a generic tool-calling request remains valid on Qwen. The
        // parent method also applies supportsToolSelection() to downgrade a
        // specific choice when thinking mode is enabled.
        if ($request->toolChoice()->isRequired()) {
            return 'auto';
        }

        return parent::toToolChoice($request);
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
