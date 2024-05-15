<?php

namespace Cognesy\Instructor\Clients\Azure;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;

class AzureApiRequest extends ApiRequest
{
    public function __construct(
        public string|array $messages = [],
        public array $tools = [],
        public string|array $toolChoice = [],
        public string|array $responseFormat = [],
        public string $model = '',
        public array $options = [],
        public string $endpoint = '',
    ) {
        if ($this->isStreamed()) {
            $options['stream_options']['include_usage'] = true;
        }
        parent::__construct(
            messages: $messages,
            tools: $tools,
            toolChoice: $toolChoice,
            responseFormat: $responseFormat,
            model: $model,
            options: $options,
            endpoint: $endpoint
        );
    }
}