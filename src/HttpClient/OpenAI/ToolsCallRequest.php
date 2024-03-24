<?php
namespace Cognesy\Instructor\HttpClient\OpenAI;

use Cognesy\Instructor\HttpClient\JsonPostRequest;

class ToolsCallRequest extends JsonPostRequest
{
    public function __construct(
        public array $messages,
        public string $model,
        public array $toolChoice,
        public array $tools,
        public array $options = [],
    )
    {
        $payload = array_merge([
            'messages' => $messages,
            'model' => $model,
            'tool_choice' => $toolChoice,
            'tools' => $tools,
        ], $options);

        parent::__construct(
            payload: $payload,
            endpoint: '/chat/completions',
        );
    }
}
