<?php
namespace Cognesy\Instructor\Clients\Anthropic\JsonCompletion;

use Cognesy\Instructor\ApiClient\Data\Requests\ApiJsonCompletionRequest;

class JsonCompletionRequest extends ApiJsonCompletionRequest
{
    protected string $endpoint = '/messages';

    public function __construct(
        public string|array  $messages = [],
        public array  $responseFormat = [],
        public string $model = '',
        public array  $options = [],
    ) {
        parent::__construct(
            $messages,
            [],
            $model,
            $options,
        );
    }
}