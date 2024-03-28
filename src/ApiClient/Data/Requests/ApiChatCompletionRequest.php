<?php
namespace Cognesy\Instructor\ApiClient\Data\Requests;

class ApiChatCompletionRequest extends ApiRequest
{
    public function __construct(
        public array $messages = [],
        public string $model = '',
        public array $options = [],
    ) {
        parent::__construct([], $this->getEndpoint());
    }

    protected function defaultBody(): array {
        return array_filter(array_merge($this->payload, [
            'messages' => $this->messages,
            'model' => $this->model,
        ], $this->options));
    }
}