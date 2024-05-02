<?php
namespace Cognesy\Instructor\ApiClient\Requests;

abstract class ApiJsonCompletionRequest extends ApiRequest
{
    protected string $prompt = "\nRespond correctly with JSON object. Response must follow JSONSchema:\n";

    public function __construct(
        public string|array $messages = [],
        public array $responseFormat = [],
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
            'response_format' => $this->getResponseFormat(),
        ], $this->options));
    }

    protected function getMessages(): array {
        return $this->appendInstructions($this->messages, $this->prompt, $this->getResponseSchema());
    }

    protected function getResponseFormat(): array {
        return $this->responseFormat;
    }

    private function getResponseSchema() : array {
        return $this->responseFormat['schema'] ?? [];
    }
}