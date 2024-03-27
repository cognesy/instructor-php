<?php
namespace Cognesy\Instructor\Clients\TogetherAI\JsonCompletion;

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
        $messages = $this->appendInstructions($messages, []);

        parent::__construct(
            $messages,
            $responseFormat,
            $model,
            $options,
        );
    }

    public function getEndpoint(): string {
        return $this->endpoint;
    }

    protected function appendInstructions(array $messages, array $jsonSchema) : array {
        $lastIndex = count($messages) - 1;
        if (!isset($messages[$lastIndex]['content'])) {
            $messages[$lastIndex]['content'] = '';
        }
        $messages[$lastIndex]['content'] .= $this->prompt;
        return $messages;
    }
}