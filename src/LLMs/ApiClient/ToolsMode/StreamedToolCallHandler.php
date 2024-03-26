<?php

namespace Cognesy\Instructor\LLMs\ApiClient\ToolsMode;

use Cognesy\Instructor\ApiClient\Contracts\CanCallTools;
use Cognesy\Instructor\ApiClient\Data\Responses\PartialApiResponse;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\LLM\RequestToLLMFailed;
use Cognesy\Instructor\Events\LLM\StreamedResponseReceived;
use Cognesy\Instructor\LLMs\AbstractStreamedCallHandler;
use Cognesy\Instructor\Utils\Arrays;
use Cognesy\Instructor\Utils\Result;
use Exception;
use Generator;

class StreamedToolCallHandler extends AbstractStreamedCallHandler
{
    private CanCallTools $client;

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

    protected function getStream() : Result {
        try {
            $stream = $this->client->toolsCall(
                messages: $this->request['messages'] ?? [],
                model: $this->request['model'] ?? '',
                tools: [$this->responseModel->functionCall],
                toolChoice: [
                    'type' => 'function',
                    'function' => ['name' => $this->responseModel->functionName]
                ],
                options: Arrays::unset($this->request, ['model', 'messages', 'tools', 'tool_choice'])
            )->stream();
        } catch (Exception $e) {
            return Result::failure($e);
        }
        return Result::success($stream);
    }

    protected function processStream(Generator $stream) : Result {
        // process stream
        $functionCalls = [];
        /** @var PartialApiResponse $response */
        foreach($stream as $response){
            // receive data
            $this->events->dispatch(new StreamedResponseReceived($response));
            $this->lastResponse = $response;

            // situation 1: new function call
            $maybeFunctionName = $response->functionName;
            if ($maybeFunctionName) {
                if (count($functionCalls) > 0) {
                    $this->finalizeFunctionCall($functionCalls, $this->responseJson);
                    $this->responseJson = ''; // reset json buffer
                }
                // start capturing new function call
                $newFunctionCall = $this->newFunctionCall($response);
                $functionCalls[] = $newFunctionCall;
            }

            // situation 2: regular data chunk
            $maybeArgumentChunk = $response->delta;
            if ($maybeArgumentChunk) {
                $this->responseJson .= $maybeArgumentChunk;
                $this->updateFunctionCall(
                    $functionCalls,
                    $this->responseJson,
                    $maybeArgumentChunk
                );
            }
        }
        // check if there are any toolCalls
        if (count($functionCalls) === 0) {
            return Result::failure(new RequestToLLMFailed($this->request, 'No tool calls found in the response'));
        }
        // finalize last function call
        if (count($functionCalls) > 0) {
            $this->finalizeFunctionCall($functionCalls, $this->responseJson);
        }
        return Result::success($functionCalls);
    }
}
