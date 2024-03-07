<?php

namespace Cognesy\Instructor\Core;

class Request
{
    public string|array $messages;
    public string|object|array $responseModel;
    public string $model = 'gpt-4-0125-preview';
    public int $maxRetries = 0;
    public array $options = [];

    public function __construct(
        string|array $messages,
        string|object|array $responseModel,
        string $model = 'gpt-4-0125-preview',
        int $maxRetries = 0,
        array $options = []
    ) {
        $this->messages = $messages;
        $this->responseModel = $responseModel;
        $this->model = $model;
        $this->maxRetries = $maxRetries;
        $this->options = $options;
    }

    public function messages() : array {
        if (is_string($this->messages)) {
            return [['role' => 'user', 'content' => $this->messages]];
        }
        return $this->messages;
    }
}
