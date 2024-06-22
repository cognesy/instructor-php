<?php
namespace Cognesy\Instructor\Clients\Ollama;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\ApiClient\Requests\Traits\HandlesResponse;
use Cognesy\Instructor\ApiClient\Requests\Traits\HandlesRequestBody;
use Cognesy\Instructor\Events\ApiClient\RequestBodyCompiled;

class OllamaApiRequest extends ApiRequest
{
    use HandlesRequestBody;
    use HandlesResponse;

    protected function defaultBody(): array {
        $body = array_filter(
            array_merge(
                $this->requestBody,
                [
                    'model' => $this->model(),
                    'messages' => $this->messages(),
                    'response_format' => $this->getResponseFormat(),
                    // TODO: Ollama does not support tool calling - add when supported
                    //'tools' => $this->tools(),
                    //'tool_choice' => $this->getToolChoice(),
                ],
            )
        );
        $this->requestConfig()->events()->dispatch(new RequestBodyCompiled($body));
        return $body;
    }
}