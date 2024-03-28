<?php
namespace Cognesy\Instructor\LLMs\ApiClient\ToolsMode;

use Cognesy\Instructor\ApiClient\Contracts\CanCallTools;
use Cognesy\Instructor\ApiClient\Data\Responses\ApiResponse;
use Cognesy\Instructor\Data\FunctionCall;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\LLMs\AbstractCallHandler;
use Cognesy\Instructor\Utils\Arrays;

class ToolCallHandler extends AbstractCallHandler
{
    protected CanCallTools $client;

    public function __construct(
        EventDispatcher $events,
        CanCallTools $client,
        array $request,
        ResponseModel $responseModel,
    ) {
        $this->client = $client;
        $this->events = $events;
        $this->request = $request;
        $this->responseModel = $responseModel;
    }

    protected function getResponse() : ApiResponse {
        return $this->client->toolsCall(
            messages: $this->request['messages'] ?? [],
            model: $this->request['model'] ?? '',
            tools: [$this->responseModel->functionCall],
            toolChoice: [
                'type' => 'function',
                'function' => ['name' => $this->responseModel->functionName]
            ],
            options: Arrays::unset($this->request, ['model', 'messages', 'tools', 'tool_choice'])
        )->respond();
    }

    protected function getFunctionCalls(ApiResponse $response) : array {
        if (empty($response->content)) {
            return [];
        }
        return [new FunctionCall(
            id: '',
            functionName: $response->functionName ?? '',
            functionArgsJson: $response->content ?? ''
        )];
//        $functionCalls = [];
//        foreach ($response->choices[0]->message->toolCalls as $data) {
//            $functionCalls[] = new FunctionCall(
//                toolCallId: $data->id ?? '',
//                functionName: $data->function->name ?? '',
//                functionArgsJson: $data->function->arguments ?? ''
//            );
//        }
//        return $functionCalls;
    }
}

