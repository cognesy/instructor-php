<?php
namespace Cognesy\Instructor\Clients\Anthropic;

use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Events\ApiClient\RequestBodyCompiled;


class AnthropicApiRequest extends ApiRequest
{
    use Traits\HandlesResponse;
    use Traits\HandlesResponseFormat;
    use Traits\HandlesScripts;
    use Traits\HandlesTools;

    protected string $defaultEndpoint = '/messages';

    protected function defaultBody(): array {
        $body = array_filter(
            array_merge(
                $this->requestBody,
                [
                    'model' => $this->model(),
                    'system' => $this->system(),
                    'messages' => $this->messages(),
                    'tools' => $this->tools(),
                    'tool_choice' => $this->getToolChoice(),
                ],
            )
        );
        $this->requestConfig()->events()->dispatch(new RequestBodyCompiled($body));
        return $body;
    }

    public function messages(): array {
        return $this->script
            ->withContext($this->scriptContext)
            ->select(['messages', 'input', 'data_ack', 'command', 'examples', 'retries'])
            ->toNativeArray(ClientType::fromRequestClass(static::class));
    }

    public function system(): array {
        return $this->script
            ->withContext($this->scriptContext)
            ->select(['system'])
            ->toNativeArray(ClientType::fromRequestClass(static::class));
    }
}
