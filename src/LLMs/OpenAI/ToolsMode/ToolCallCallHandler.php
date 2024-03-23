<?php

namespace Cognesy\Instructor\LLMs\OpenAI\ToolsMode;

use Cognesy\Instructor\Data\FunctionCall;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\LLMs\AbstractCallHandler;
use OpenAI\Client;
use OpenAI\Responses\Chat\CreateResponse;

class ToolCallCallHandler extends AbstractCallHandler
{
    protected Client $client;

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
        if (!$this->hasToolCalls($response)) {
            return [];
        }
        $functionCalls = [];
        foreach ($response->choices[0]->message->toolCalls as $data) {
            $functionCalls[] = new FunctionCall(
                toolCallId: $data->id ?? '',
                functionName: $data->function->name ?? '',
                functionArgsJson: $data->function->arguments ?? ''
            );
        }
        return $functionCalls;
    }

    protected function hasToolCalls(mixed $response) : bool {
        /** @var CreateResponse $response */
        return isset($response->choices[0]->message->toolCalls);
    }

    protected function getFinishReason(mixed $response) : string {
        /** @var CreateResponse $response */
        return $response->choices[0]->finishReason ?? '';
    }
}
