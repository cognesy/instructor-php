<?php
namespace Cognesy\Instructor\Clients\Anthropic;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Events\ApiClient\RequestBodyCompiled;


class AnthropicApiRequest extends ApiRequest
{
    use Traits\HandlesResponse;
    use Traits\HandlesRequestBody;

    protected string $defaultEndpoint = '/messages';

    private string $system;

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
}
