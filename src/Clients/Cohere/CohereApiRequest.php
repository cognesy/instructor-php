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
        $system = '';
        $chatHistory = [];
        $messages = Messages::asString($this->messages());
        $body = array_filter(
            array_merge(
                $this->requestBody,
                [
                    'model' => $this->model(),
                    'preamble' => $system,
                    'chat_history' => $chatHistory,
                    'message' => $messages,
                    'tools' => $this->tools(),
                    'response_format' => $this->getResponseFormat(),
                ],
            )
        );
        $this->requestConfig()->events()->dispatch(new RequestBodyCompiled($body));
        return $body;
    }
}
