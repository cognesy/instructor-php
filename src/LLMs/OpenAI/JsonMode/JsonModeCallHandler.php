<?php
namespace Cognesy\Instructor\LLMs\OpenAI\JsonMode;

use Cognesy\Instructor\Data\FunctionCall;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\LLMs\AbstractCallHandler;
use Cognesy\Instructor\Utils\Json;
use OpenAI\Client;
use OpenAI\Responses\Chat\CreateResponse;

class JsonModeCallHandler extends AbstractCallHandler
{
    private Client $client;

    public function __construct(
        EventDispatcher $events,
        Client $client,
        array $request,
        ResponseModel $responseModel,
    ) {
        $this->client = $client;
        $this->events = $events;
        $this->request = $request;
        $this->responseModel = $responseModel;
    }

    protected function getResponse() : CreateResponse {
        return $this->client->chat()->create($this->request);
    }

    protected function getFunctionCalls(mixed $response) : array {
        /** @var CreateResponse $response */
        if (!($content = $response->choices[0]->message->content)) {
            return [];
        }
        $jsonData = Json::extract($content);
        $toolCalls = [];
        $toolCalls[] = new FunctionCall(
            toolCallId: '', // ???
            functionName: $this->responseModel->functionName,
            functionArgsJson: $jsonData
        );
        return $toolCalls;
    }

    protected function getFinishReason(mixed $response) : string {
        /** @var CreateResponse $response */
        return $response->choices[0]->finishReason ?? '';
    }
}
