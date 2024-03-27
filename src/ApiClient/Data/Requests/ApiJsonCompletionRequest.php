<?php
namespace Cognesy\Instructor\ApiClient\Data\Requests;

abstract class ApiJsonCompletionRequest extends ApiRequest
{
    protected string $prompt = "\nRespond with JSON. Response must follow provided JSONSchema.\n";

    public function __construct(
        public array  $messages = [],
        public array  $responseFormat = [],
        public string $model = '',
        public array  $options = [],
    )
    {
        $payload = array_merge([
            'messages' => $messages,
            'model' => $model,
            'response_format' => $responseFormat
        ], $options);

        parent::__construct(
            payload: $payload,
            endpoint: $this->getEndpoint(),
        );
    }

    abstract public function getEndpoint() : string;

    protected function appendInstructions(array $messages, array $jsonSchema) : array {
        $lastIndex = count($messages) - 1;
        if (!isset($messages[$lastIndex]['content'])) {
            $messages[$lastIndex]['content'] = '';
        }
        $messages[$lastIndex]['content'] .= $this->prompt . json_encode($jsonSchema);
        return $messages;
    }
}