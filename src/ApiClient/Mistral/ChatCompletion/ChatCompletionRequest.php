<?php

namespace Cognesy\Instructor\ApiClient\Mistral;

use Cognesy\Instructor\ApiClient\JsonRequest;

class ChatCompletionRequest extends JsonRequest
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