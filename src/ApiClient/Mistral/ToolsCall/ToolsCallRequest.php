<?php
namespace Cognesy\Instructor\ApiClient\Mistral\ToolsCall;

use Cognesy\Instructor\ApiClient\JsonRequest;

class ToolsCallRequest extends JsonRequest
{
    public function __construct(
        public array  $messages,
        public string $model,
        public array  $tools,
        public array  $options = [],
    )
    {
        $payload = array_merge([
            'messages' => $messages,
            'model' => $model,
            'tool_choice' => 'any',
            'tools' => $tools,
        ], $options);

        parent::__construct(
            payload: $payload,
            endpoint: '/chat/completions',
        );
    }
}