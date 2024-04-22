<?php
namespace Cognesy\Instructor\ApiClient\Data\Requests;

class ApiChatCompletionRequest extends ApiRequest
{
    public function __construct(
        public string|array $messages = [],
        public string $model = '',
        public array $options = [],
    ) {
        $this->messages = $this->normalizeMessages($messages);
        parent::__construct($options);
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