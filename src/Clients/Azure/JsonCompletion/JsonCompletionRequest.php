<?php
namespace Cognesy\Instructor\Clients\Azure\JsonCompletion;

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
        $responseFormat = ['type' => 'json_object'];

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
}