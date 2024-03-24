<?php

namespace Cognesy\Instructor\ApiClient\Anthropic\Message;

use Cognesy\Instructor\ApiClient\JsonRequest;

class MessageCompletionRequest extends JsonRequest
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
            endpoint: '/messages',
        );
    }
}