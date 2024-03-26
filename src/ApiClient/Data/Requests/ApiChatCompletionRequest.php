<?php
namespace Cognesy\Instructor\ApiClient\Data\Requests;

abstract class ApiChatCompletionRequest extends ApiRequest
{
    public function __construct(
        public array $messages = [],
        public string $model = '',
        public array $options = [],
    ) {
        $payload = array_merge([
            'messages' => $messages,
            'model' => $model,
        ], $options);

        parent::__construct(
            payload: $payload,
            endpoint: $this->getEndpoint(),
        );
    }

    static public function fromArray(array $payload): static {
        $messages = $payload['messages'] ?? [];
        $model = $payload['model'] ?? '';
        $options = $payload ?? [];
        unset($options['model']);
        unset($options['messages']);
        return new static(
            messages: $messages,
            model: $model,
            options: $options,
        );
    }

    abstract public function getEndpoint(): string;
}