<?php
namespace Cognesy\Instructor\ApiClient\Requests;

abstract class ApiJsonCompletionRequest extends ApiRequest
{
    public function __construct(
        public string|array $messages = [],
        public array $responseFormat = [],
        public string $model = '',
        public array $options = [],
        public string $endpoint = '',
    ) {
        $this->messages = $this->normalizeMessages($messages);
        parent::__construct($options, $endpoint);
    }

    protected function defaultBody(): array {
        return array_filter(array_merge([
            'messages' => $this->messages(),
            'model' => $this->model,
            'response_format' => $this->getResponseFormat(),
        ], $this->options));
    }

    protected function messages(): array {
        return $this->messages;
    }

    protected function getResponseFormat(): array {
        return $this->responseFormat;
    }

    protected function getResponseSchema() : array {
        return $this->responseFormat['schema'] ?? [];
    }
}