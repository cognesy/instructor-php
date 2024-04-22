<?php
namespace Cognesy\Instructor\ApiClient\Data\Requests;

class ApiToolsCallRequest extends ApiRequest
{
    public function __construct(
        public string|array  $messages = [],
        public array  $tools = [],
        public string|array  $toolChoice = [],
        public string $model = '',
        public array  $options = [],
    ) {
        $this->messages = $this->normalizeMessages($messages);
        parent::__construct($options);
    }

    protected function defaultBody(): array {
        return array_filter(array_merge([
            'messages' => $this->getMessages(),
            'model' => $this->model,
            'tools' => $this->tools,
            'tool_choice' => $this->getToolChoice(),
        ], $this->options));
    }

    protected function getMessages(): array {
        return $this->messages;
    }

    protected function getToolChoice(): string|array {
        return $this->toolChoice ?: 'any';
    }
}