<?php
namespace Cognesy\Instructor\Clients\OpenRouter\JsonCompletion;

use Cognesy\Instructor\ApiClient\Data\Requests\ApiJsonCompletionRequest;

class JsonCompletionRequest extends ApiJsonCompletionRequest
{
    protected string $endpoint = '/chat/completions';

    public function __construct(
        public array  $messages = [],
        public array  $responseFormat = [],
        public string $model = '',
        public array  $options = [],
    )
    {
        $messages = $this->appendInstructions($messages, $responseFormat['schema']);
        parent::__construct(
            $messages,
            ['type' => 'json_object'],
            $model,
            $options,
        );
    }
}