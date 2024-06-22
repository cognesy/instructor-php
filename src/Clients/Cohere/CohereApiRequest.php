<?php
namespace Cognesy\Instructor\Clients\Cohere;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Data\Messages\Messages;
use Cognesy\Instructor\Events\ApiClient\RequestBodyCompiled;


class CohereApiRequest extends ApiRequest
{
    use Traits\HandlesResponse;
    use Traits\HandlesRequestBody;

    protected string $defaultEndpoint = '/chat';

    protected function defaultBody(): array {
        $body = array_filter(
            array_merge(
                $this->requestBody,
                [
                    'model' => $this->model(),
                    'preamble' => $this->preamble(),
                    'chat_history' => $this->chatHistory(),
                    'message' => Messages::asString($this->messages()),
                    'tools' => $this->tools(),
                ],
            )
        );
        $this->requestConfig()->events()->dispatch(new RequestBodyCompiled($body));
        return $body;
    }
}
