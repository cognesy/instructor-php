<?php
namespace Cognesy\Instructor\LLMs\OpenAI\JsonMode;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\LLMs\AbstractJsonHandler;
use Cognesy\Instructor\Utils\Json;
use OpenAI\Client;
use OpenAI\Responses\Chat\CreateResponse;

class JsonModeHandler extends AbstractJsonHandler
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

    protected function getJsonData(mixed $response): string
    {
        if (!($content = $this->getResponseContent($response))) {
            return '';
        }
        return Json::find($content);
    }

    protected function getResponseContent(mixed $response) : string {
        /** @var CreateResponse $response */
        return $response->choices[0]->message->content ?? '';
    }

    protected function getFinishReason(mixed $response) : string {
        /** @var CreateResponse $response */
        return $response->choices[0]->finishReason ?? '';
    }
}
