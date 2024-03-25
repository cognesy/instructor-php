<?php

namespace Cognesy\Instructor\LLMs\OpenAI\ToolsMode;

use Cognesy\Instructor\Data\LLMResponse;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\LLM\RequestSentToLLM;
use Cognesy\Instructor\Events\LLM\RequestToLLMFailed;
use Cognesy\Instructor\Events\LLM\StreamedResponseFinished;
use Cognesy\Instructor\Events\LLM\StreamedResponseReceived;
use Cognesy\Instructor\LLMs\AbstractStreamedCallHandler;
use Cognesy\Instructor\Utils\Result;
use Exception;
use OpenAI\Client;

class StreamedToolCallHandler extends AbstractStreamedCallHandler
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

    protected function getStream() : Result {
        try {
            $stream = $this->client->chat()->createStreamed($this->request);
        } catch (Exception $e) {
            return Result::failure($e);
        }
        return Result::success($stream);
    }

    protected function processStream($stream) : Result {
        // process stream
        $functionCalls = [];
        foreach($stream as $response){
            // receive data
            $this->events->dispatch(new StreamedResponseReceived($response->toArray()));
            $this->lastResponse = $response;

            // situation 1: new function call
            $maybeFunctionName = $this->getFunctionName($response);
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
            $maybeArgumentChunk = $this->getArgumentChunk($response);
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

    protected function getFinishReason(mixed $response) : string {
        return $response->choices[0]->finishReason ?? '';
    }

    protected function getFunctionName($response) : ?string {
        return $response->choices[0]->delta->toolCalls[0]->function->name ?? null;
    }

    protected function getArgumentChunk($response) : ?string {
        return $response->choices[0]->delta->toolCalls[0]->function->arguments ?? null;
    }
}
