<?php
namespace Cognesy\Instructor\ApiClient\Data\Requests;

abstract class ApiToolsCallRequest extends ApiRequest
{
    public function __construct(
        public array  $messages = [],
        public string $model = '',
        public array  $tools = [],
        public array  $toolChoice = [],
        public array  $options = [],
    )
    {
        $payload = array_merge([
            'messages' => $messages,
            'model' => $model,
            'tool_choice' => $toolChoice ?: 'any',
            'tools' => $tools,
        ], $options);

        parent::__construct(
            payload: $payload,
            endpoint: $this->getEndpoint(),
        );
    }

    static public function fromArray(array $payload): static {
        $messages = $payload['messages'] ?? [];
        $model = $payload['model'] ?? '';
        $tools = $payload['tools'] ?? [];
        $toolChoice = $payload['tool_choice'] ?? [];
        $options = $payload ?? [];
        unset($options['model']);
        unset($options['messages']);
        unset($options['tools']);
        unset($options['tool_choice']);
        return new static(
            messages: $messages,
            model: $model,
            tools: $tools,
            toolChoice: $toolChoice,
            options: $options,
        );
    }

    abstract public function getEndpoint() : string;
}