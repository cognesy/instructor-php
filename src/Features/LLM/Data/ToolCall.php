<?php

namespace Cognesy\Instructor\Features\LLM\Data;

use Cognesy\Instructor\Utils\Json\Json;

class ToolCall
{
    public function __construct(
        public string $name,
        public string $args,
    ) {}

    public static function fromArray(array $toolCall) : ToolCall {
        return new ToolCall(
            name: $toolCall['name'],
            args: match(true) {
                is_array($toolCall['arguments'] ?? false) => empty($toolCall['arguments']) ? '' : Json::encode($toolCall['arguments']),
                is_string($toolCall['arguments'] ?? false) => $toolCall['arguments'] ?? '',
                is_null($toolCall['arguments']) => '',
                default => throw new \InvalidArgumentException('ToolCall args must be a string or an array')
            }
        );
    }
}
