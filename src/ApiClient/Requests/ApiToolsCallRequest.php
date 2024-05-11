<?php
namespace Cognesy\Instructor\ApiClient\Requests;

abstract class ApiToolsCallRequest extends ApiRequest
{
    public function __construct(
        public string|array  $messages = [],
        public array $tools = [],
        public string|array  $toolChoice = [],
        public string $model = '',
        public array  $options = [],
        public string $endpoint = '',
    ) {
        $this->messages = $this->normalizeMessages($messages);
        parent::__construct($options, $endpoint);
    }

    protected function defaultBody(): array {
        return array_filter(array_merge([
            'messages' => $this->messages(),
            'model' => $this->model,
            'tools' => $this->tools,
            'tool_choice' => $this->getToolChoice(),
        ], $this->options));
    }

    protected function messages(): array {
        return $this->messages;
    }

    protected function getToolChoice(): string|array {
        return $this->toolChoice ?: 'any';
    }
}
