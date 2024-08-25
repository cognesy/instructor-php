<?php
namespace Cognesy\Instructor\Clients\Anthropic;

use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Data\Messages\Messages;
use Cognesy\Instructor\Events\ApiClient\RequestBodyCompiled;


class AnthropicApiRequest extends ApiRequest
{
    use Traits\HandlesResponse;
    use Traits\HandlesRequestBody;

    protected string $defaultEndpoint = '/messages';

    protected function defaultBody(): array {
        $system = Messages::fromArray($this->messages)
            ->forRoles(['system'])
            ->toString();

        $messages = Messages::fromArray($this->messages)
            ->exceptRoles(['system'])
            ->toNativeArray(
                clientType: ClientType::fromRequestClass($this),
                mergePerRole: true
            );

        $body = array_filter(
            array_merge(
                $this->requestBody,
                [
                    'model' => $this->model(),
                    'max_tokens' => $this->maxTokens,
                    'system' => $system,
                    'messages' => $messages,
                    'tools' => $this->tools(),
                    'tool_choice' => $this->getToolChoice(),
                ],
            )
        );
        $this->requestConfig()->events()->dispatch(new RequestBodyCompiled($body));
        return $body;
    }
}
