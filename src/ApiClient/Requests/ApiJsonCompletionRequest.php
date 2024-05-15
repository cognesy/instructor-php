<?php
namespace Cognesy\Instructor\ApiClient\Requests;

use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
class ApiJsonCompletionRequest extends ApiRequest
{
    public function __construct(
        public string|array $messages = [],
        public string|array $responseFormat = [],
        public string $model = '',
        public array $options = [],
        public string $endpoint = '',
    ) {
        parent::__construct(
            messages: $messages,
            responseFormat: $responseFormat,
            model: $model,
            options: $options,
            endpoint: $endpoint
        );
    }

//    protected function defaultBody(): array {
//        return array_filter(array_merge([
//            'messages' => $this->messages(),
//            'model' => $this->model,
//            'response_format' => $this->getResponseFormat(),
//        ], $this->options));
//    }
//
//    protected function messages(): array {
//        return $this->messages;
//    }
//
//    protected function getResponseFormat(): array {
//        return $this->responseFormat;
//    }

//    protected function getResponseSchema() : array {
//        return $this->responseFormat['schema'] ?? [];
//    }
}