<?php

namespace Cognesy\Instructor\Clients\Cohere;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Override;

class CohereApiRequest extends ApiRequest
{
    use Traits\HandlesResponse;
    use Traits\HandlesTools;
    use Traits\HandlesResponseFormat;

    protected string $defaultEndpoint = '/chat';

    public function __construct(
        public array $messages = [],
        public array $tools = [],
        public string|array $toolChoice = [],
        public string|array $responseFormat = [],
        public string $model = '',
        public array $options = [],
        public string $endpoint = '',
    ) {
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

    #[Override]
    protected function defaultBody(): array {
        return array_filter(
            array_merge(
                [
                    'model' => $this->model,
                    'tools' => $this->tools()
                ],
                $this->options,
            )
        );
    }
}
