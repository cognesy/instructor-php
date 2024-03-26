<?php
namespace Cognesy\Instructor\LLMs\ApiClient\MdJsonMode;

use Cognesy\Instructor\ApiClient\Contracts\CanCallChatCompletion;
use Cognesy\Instructor\ApiClient\Data\Responses\ApiResponse;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\LLMs\AbstractJsonHandler;
use Cognesy\Instructor\Utils\Arrays;

class MdJsonModeHandler extends AbstractJsonHandler
{
    private CanCallChatCompletion $client;

    public function __construct(
        EventDispatcher $events,
        CanCallChatCompletion $client,
        array $request,
        ResponseModel $responseModel,
    ) {
        $this->client = $client;
        $this->events = $events;
        $this->request = $request;
        $this->responseModel = $responseModel;
    }

    protected function getResponse() : ApiResponse {
        return $this->client->chatCompletion(
            messages: $this->request['messages'] ?? [],
            model: $this->request['model'] ?? '',
            options: Arrays::unset($this->request, ['model', 'messages'])
        )->respond();
    }
}
