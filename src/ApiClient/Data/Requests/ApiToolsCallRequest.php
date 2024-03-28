<?php
namespace Cognesy\Instructor\ApiClient\Data\Requests;

class ApiToolsCallRequest extends ApiRequest
{
    public function __construct(
        public array  $messages = [],
        public string $model = '',
        public array  $tools = [],
        public array  $toolChoice = [],
        public array  $options = [],
    ) {
        parent::__construct([], $this->getEndpoint());
        $this->toolChoice = $toolChoice ?: 'any';
    }

    protected function defaultBody(): array {
        return array_filter(array_merge($this->payload, [
            'messages' => $this->messages,
            'model' => $this->model,
            'tools' => $this->tools,
            'tool_choice' => $this->toolChoice,
        ], $this->options));
    }
}