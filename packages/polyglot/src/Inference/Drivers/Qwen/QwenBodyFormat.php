<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Qwen;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use InvalidArgumentException;
use Override;

class QwenBodyFormat extends OpenAICompatibleBodyFormat
{
    #[Override]
    public function toRequestBody(InferenceRequest $request): array {
        $this->assertRequestIsRenderable($request);
        $requestBody = parent::toRequestBody($request);
        if ($this->shouldMapThinkingToEnableThinking($requestBody)) {
            $requestBody['enable_thinking'] = $this->toBoolean($requestBody['thinking']);
        }
        unset($requestBody['thinking']);

        return $requestBody;
    }

    private function assertRequestIsRenderable(InferenceRequest $request): void {
        if ($request->toolChoice()->isRequired()) {
            throw new InvalidArgumentException(
                'Qwen cannot render required tool choice; use auto, none, or a specific tool.',
            );
        }
        if ($request->toolChoice()->isSpecific() && $this->thinkingEnabled($request)) {
            throw new InvalidArgumentException(
                'Qwen cannot render a specific tool choice while thinking is enabled.',
            );
        }
        if ($request->hasTools() && $request->hasNonTextResponseFormat()) {
            throw new InvalidArgumentException(
                'Qwen cannot render a non-text response format together with tools.',
            );
        }
    }

    private function thinkingEnabled(InferenceRequest $request): bool {
        $options = array_merge($this->config->options, $request->options());
        $thinking = $options['enable_thinking'] ?? $options['thinking'] ?? false;

        return $this->toBoolean($thinking);
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
