<?php
namespace Cognesy\Instructor\ApiClient\OpenAI\JsonCompletion;

use Cognesy\Instructor\ApiClient\JsonRequest;

class JsonCompletionRequest extends JsonRequest
{
    public function __construct(
        public array  $messages,
        public string $model,
        public array  $options = [],
    ) {
        $payload = array_merge([
            'messages' => $messages,
            'model' => $model,
            'response_format' => ['type' => 'json_object']
        ], $options);

        parent::__construct(
            payload: $payload,
            endpoint: '/chat/completions',
        );
    }
}