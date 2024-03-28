<?php
namespace Cognesy\Instructor\LLMs\ApiClient\JsonMode;

use Cognesy\Instructor\ApiClient\Contracts\CanCallJsonCompletion;
use Cognesy\Instructor\ApiClient\Data\Responses\ApiResponse;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\LLMs\AbstractCallHandler;
use Cognesy\Instructor\Utils\Arrays;

class JsonModeHandler extends AbstractCallHandler
{
    private CanCallJsonCompletion $client;

    public function __construct(
        EventDispatcher $events,
        CanCallJsonCompletion $client,
        array $request,
        ResponseModel $responseModel,
    ) {
        $this->client = $client;
        $this->events = $events;
        $this->request = $request;
        $this->responseModel = $responseModel;
    }

    protected function getResponse() : ApiResponse {
        return $this->client->jsonCompletion(
            messages: $this->request['messages'] ?? [],
            responseFormat: $this->request['response_format'] ?? [],
            model: $this->request['model'] ?? '',
            options: Arrays::unset($this->request, ['model', 'messages', 'response_format'])
        )->respond();
    }
}
