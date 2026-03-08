<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Glm;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;

class GlmBodyFormat extends OpenAICompatibleBodyFormat
{
    #[\Override]
    public function toRequestBody(InferenceRequest $request) : array {
        $requestBody = parent::toRequestBody($request);
        $requestBody = $this->mapEnableThinkingToThinking($requestBody);
        $requestBody = $this->withToolStreamByDefault($requestBody);

        return $requestBody;
    }

    #[\Override]
    protected function supportsNonTextResponseForTools(InferenceRequest $request) : bool {
        return false;
    }

    /**
     * @param array<string,mixed> $requestBody
     * @return array<string,mixed>
     */
    private function mapEnableThinkingToThinking(array $requestBody): array {
        if ($this->shouldMapEnableThinkingToThinking($requestBody)) {
            $requestBody['thinking'] = $this->toBoolean($requestBody['enable_thinking']);
        }
        unset($requestBody['enable_thinking']);

        return $requestBody;
    }

    /**
     * @param array<string,mixed> $requestBody
     * @return array<string,mixed>
     */
    private function withToolStreamByDefault(array $requestBody): array {
        if (array_key_exists('tool_stream', $requestBody)) {
            return $requestBody;
        }
        if (!$this->shouldEnableToolStream($requestBody)) {
            return $requestBody;
        }

        $requestBody['tool_stream'] = true;
        return $requestBody;
    }

    /**
     * @param array<string,mixed> $requestBody
     */
    private function shouldMapEnableThinkingToThinking(array $requestBody): bool {
        if (array_key_exists('thinking', $requestBody)) {
            return false;
        }

        return array_key_exists('enable_thinking', $requestBody);
    }

    /**
     * @param array<string,mixed> $requestBody
     */
    private function shouldEnableToolStream(array $requestBody): bool {
        $hasTools = isset($requestBody['tools']) && is_array($requestBody['tools']) && $requestBody['tools'] !== [];
        $isStreaming = (bool) ($requestBody['stream'] ?? false);

        return $hasTools && $isStreaming;
    }

    private function toBoolean(mixed $value): bool {
        return match (true) {
            is_bool($value) => $value,
            is_int($value) => $value !== 0,
            is_float($value) => $value !== 0.0,
            is_string($value) => in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on', 'enabled'], true),
            default => (bool) $value,
        };
    }
}
