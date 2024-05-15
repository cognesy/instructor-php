<?php
namespace Cognesy\Instructor\ApiClient\Requests;

use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
class ApiChatCompletionRequest extends ApiRequest
{
    public function __construct(
        public string|array $messages = [],
        public string $model = '',
        public array $options = [],
        public string $endpoint = '',
    ) {
        parent::__construct(
            messages: $messages,
            model: $model,
            options: $options,
            endpoint: $endpoint
        );
    }

//    protected function defaultBody(): array {
//        return array_filter(array_merge([
//            'messages' => $this->messages(),
//            'model' => $this->model,
//        ], $this->options));
//    }
//
//    protected function messages(): array {
//        return $this->messages;
//    }
}