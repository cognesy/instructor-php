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

    #[\Override]
    protected function supportsToolSelection(InferenceRequest $request): bool {
        if (! $request->toolChoice()->isSpecific()) {
            return true;
        }

        $options = array_merge($this->config->options, $request->options());
        $thinking = $options['enable_thinking'] ?? $options['thinking'] ?? false;

        // Qwen does not allow forcing a specific function while thinking mode is enabled.
        return ! $this->toBoolean($thinking);
    }

    #[\Override]
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

    #[\Override]
    protected function toResponseFormatType(InferenceRequest $request): ?string {
        $type = parent::toResponseFormatType($request);

        // JSON Schema is available only on the current Qwen3.8-Max and Qwen3.7
        // Max/Plus model families. Older Qwen models accept JSON Object mode,
        // so degrade schema requests rather than sending an unsupported shape.
        if ($type === 'json_schema' && ! $this->supportsJsonSchema($request)) {
            return 'json_object';
        }

        return $type;
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

    private function supportsJsonSchema(InferenceRequest $request): bool {
        $model = $request->model() ?: $this->config->model;

        return preg_match('/^qwen3\.(?:8-max|7-(?:max|plus))(?:-|$)/', $model) === 1;
    }
}
