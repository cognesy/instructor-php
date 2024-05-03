<?php
namespace Cognesy\Instructor\ApiClient\Requests;

abstract class ApiChatCompletionRequest extends ApiRequest
{
    public function __construct(
        public string|array $messages = [],
        public string $model = '',
        public array $options = [],
        public string $endpoint = '',
    ) {
        $this->messages = $this->normalizeMessages($messages);
        parent::__construct($options, $endpoint);
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