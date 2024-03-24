<?php

namespace Cognesy\Instructor\HttpClient\Mistral;

use Cognesy\Instructor\HttpClient\JsonPostRequest;

class ChatCompletionRequest extends JsonPostRequest
{
    public function __construct(
        public array $messages,
        public string $model,
        public array $options = [],
    ) {
        $payload = array_merge([
            'messages' => $messages,
            'model' => $model,
        ], $options);

        parent::__construct(
            payload: $payload,
            endpoint: '/chat/completions',
        );
    }
}