<?php
namespace Cognesy\Instructor\Clients\Anthropic;

use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\ApiClient\Requests\ApiRequest;


class AnthropicApiRequest extends ApiRequest
{
    use Traits\HandlesResponse;
    use Traits\HandlesResponseFormat;
    use Traits\HandlesScripts;
    use Traits\HandlesTools;

    protected string $defaultEndpoint = '/messages';

    protected function defaultBody(): array {
        return array_filter(
            array_merge(
                $this->requestBody,
                [
                    'messages' => $this->messages(),
                    'model' => $this->model,
                    'tools' => $this->tools(),
                    'tool_choice' => $this->getToolChoice(),
                ],
            )
        );
    }

    public function messages(): array {
        return $this->script
            ->withContext($this->scriptContext)
            ->select(['messages', 'data_ack', 'command', 'examples'])
            ->toNativeArray(ClientType::fromRequestClass(static::class));
    }
}
