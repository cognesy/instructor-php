<?php
namespace Cognesy\Instructor\ApiClient\Data\Requests;

class ApiToolsCallRequest extends ApiRequest
{
    public function __construct(
        public string|array  $messages = [],
        public array  $tools = [],
        public array  $toolChoice = [],
        public string $model = '',
        public array  $options = [],
    ) {
        if (!is_array($messages)) {
            $this->messages = ['role' => 'user', 'content' => $messages];
        }
        $this->toolChoice = $toolChoice ?: 'any';
        parent::__construct([], $this->getEndpoint());
    }

    protected function defaultBody(): array {
        return array_filter(array_merge($this->payload, [
            'messages' => $this->getMessages(),
            'model' => $this->model,
            'tools' => $this->tools,
            'tool_choice' => $this->toolChoice,
        ], $this->options));
    }

    protected function getMessages(): array {
        return $this->messages;
    }
}