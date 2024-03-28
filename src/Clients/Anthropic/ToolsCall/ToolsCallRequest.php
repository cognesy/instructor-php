<?php
namespace Cognesy\Instructor\Clients\Anthropic\ToolsCall;

use Cognesy\Instructor\ApiClient\Data\Requests\ApiToolsCallRequest;
use Cognesy\Instructor\Utils\Arrays;

class ToolsCallRequest extends ApiToolsCallRequest
{
    protected string $endpoint = '/messages';

    public function __construct(
        public array  $messages = [],
        public string $model = '',
        public array  $tools = [],
        public array  $toolChoice = [],
        public array  $options = [],
    ) {

        parent::__construct(
            messages: $messages,
            model: $model,
            tools: $tools,
            toolChoice: $toolChoice,
            options: $options,
        );
    }

    public function getEndpoint(): string {
        return $this->endpoint;
    }

    protected function defaultBody(): array {
        $this->payload = Arrays::unset($this->payload, ['tools', 'tool_choice']);
        return $this->payload;
    }
}
