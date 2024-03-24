<?php
namespace Cognesy\Instructor\HttpClient\Mistral;

use Cognesy\Instructor\HttpClient\JsonPostRequest;

class ToolsCallRequest extends JsonPostRequest
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