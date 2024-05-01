<?php
namespace Cognesy\Instructor\ApiClient\Requests;

abstract class ApiChatCompletionRequest extends ApiRequest
{
    public function __construct(
        public string|array $messages = [],
        public string $model = '',
        public array $options = [],
    ) {
        $this->messages = $this->normalizeMessages($messages);
        parent::__construct($options);
    }

    public static function create(
        string|array $messages,
        string $model = '',
        array $options = []
    ): static {
        return new static($messages, $model, $options);
    }

    protected function defaultBody(): array {
        return array_filter(array_merge([
            'messages' => $this->getMessages(),
            'model' => $this->model,
        ], $this->options));
    }

    protected function getMessages(): array {
        return $this->messages;
    }
}