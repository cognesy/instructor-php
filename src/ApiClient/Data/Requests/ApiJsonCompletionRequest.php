<?php
namespace Cognesy\Instructor\ApiClient\Data\Requests;

class ApiJsonCompletionRequest extends ApiRequest
{
    protected string $prompt = "\nRespond with JSON. Response must follow provided JSONSchema.\n";

    public function __construct(
        public array  $messages = [],
        public array  $responseFormat = [],
        public string $model = '',
        public array  $options = [],
    ) {
        parent::__construct([], $this->getEndpoint());
    }

    protected function appendInstructions(array $messages, array $jsonSchema) : array {
        $lastIndex = count($messages) - 1;
        if (!isset($messages[$lastIndex]['content'])) {
            $messages[$lastIndex]['content'] = '';
        }
        $messages[$lastIndex]['content'] .= $this->prompt . json_encode($jsonSchema);
        return $messages;
    }

    protected function defaultBody(): array {
        return array_filter(array_merge($this->payload, [
            'messages' => $this->messages,
            'model' => $this->model,
            'response_format' => $this->responseFormat,
        ], $this->options));
    }
}