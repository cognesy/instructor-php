<?php

namespace Cognesy\Instructor\LLMs\OpenAI\ToolsMode;

use Cognesy\Instructor\Core\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\LLM\ChunkReceived;
use Cognesy\Instructor\Events\LLM\PartialJsonReceived;
use Cognesy\Instructor\Events\LLM\RequestSentToLLM;
use Cognesy\Instructor\Events\LLM\RequestToLLMFailed;
use Cognesy\Instructor\Events\LLM\StreamedFunctionCallCompleted;
use Cognesy\Instructor\Events\LLM\StreamedFunctionCallStarted;
use Cognesy\Instructor\Events\LLM\StreamedFunctionCallUpdated;
use Cognesy\Instructor\Events\LLM\StreamedResponseFinished;
use Cognesy\Instructor\Events\LLM\StreamedResponseReceived;
use Cognesy\Instructor\LLMs\FunctionCall;
use Cognesy\Instructor\LLMs\LLMResponse;
use Cognesy\Instructor\Utils\Result;
use Exception;
use OpenAI\Client;

class StreamedToolCallHandler
{
    public function __construct(
        private EventDispatcher $eventDispatcher,
        private Client $client,
        private array $request,
        private ResponseModel $responseModel,
    ) {}

    /**
     * Handle streamed chat call
     */
    public function handle() : Result {
        // get stream
        try {
            $this->eventDispatcher->dispatch(new RequestSentToLLM($this->request));
            $stream = $this->client->chat()->createStreamed($this->request);
        } catch (Exception $e) {
            $event = new RequestToLLMFailed($this->request, [$e->getMessage()]);
            $this->eventDispatcher->dispatch($event);
            return Result::failure($event);
        }

        // process stream
        $responseJson = '';
        $toolCalls = [];
        foreach($stream as $response){
            $this->eventDispatcher->dispatch(new StreamedResponseReceived($response->toArray()));
            $maybeFunctionName = $response->choices[0]->delta->toolCalls[0]->function->name ?? null;
            $maybeArgumentChunk = $response->choices[0]->delta->toolCalls[0]->function->arguments ?? null;

            // situation 1: new function call
            if ($maybeFunctionName) {
                if (count($toolCalls) > 0) {
                    $this->finalizeFunctionCall($toolCalls, $responseJson);
                    $responseJson = ''; // reset json buffer
                }
                // start capturing new function call
                $newFunctionCall = $this->newFunctionCall($response);
                $toolCalls[] = $newFunctionCall;
            }

            // situation 2: regular data chunk
            if ($maybeArgumentChunk) {
                $this->eventDispatcher->dispatch(new ChunkReceived($maybeArgumentChunk));
                $responseJson .= $maybeArgumentChunk;
                $this->eventDispatcher->dispatch(new PartialJsonReceived($responseJson));
                $this->updateFunctionCall($toolCalls, $responseJson);
            }

            // situation 3: finishReason other than 'stop'
            //$finishReason = $response->choices[0]->finishReason ?? null;
            //if ($finishReason) {
            //    $this->finalizeFunctionCall($toolCalls, $responseJson);
            //    return Result::success(new LLMResponse($toolCalls, $finishReason, $response->toArray(), true));
            //}
        }
        // check if there are any toolCalls
        if (count($toolCalls) === 0) {
            return Result::failure(new RequestToLLMFailed($this->request, ['No tool calls found in the response']));
        }
        // finalize last function call
        if (count($toolCalls) > 0) {
            $this->finalizeFunctionCall($toolCalls, $responseJson);
        }
        // handle finishReason other than 'stop'
        $response = new LLMResponse(
            toolCalls: $toolCalls,
            finishReason: ($response->choices[0]->finishReason ?? null),
            rawData: $response->toArray(),
            isComplete: true,
        );
        $this->eventDispatcher->dispatch(new StreamedResponseFinished($response));
        return Result::success($response);
    }

    private function newFunctionCall($response) : FunctionCall {
        $newFunctionCall = new FunctionCall(
            toolCallId: $response->choices[0]->delta->toolCalls[0]->id,
            functionName: $response->choices[0]->delta->toolCalls[0]->function->name,
            functionArguments: ''
        );
        $this->eventDispatcher->dispatch(new StreamedFunctionCallStarted($newFunctionCall));
        return $newFunctionCall;
    }

    private function finalizeFunctionCall(array $toolCalls, string $responseJson) : void {
        /** @var FunctionCall $currentFunctionCall */
        $currentFunctionCall = $toolCalls[count($toolCalls) - 1];
        $currentFunctionCall->functionArguments = $responseJson;
        $this->eventDispatcher->dispatch(new StreamedFunctionCallCompleted($currentFunctionCall));
    }

    private function updateFunctionCall(array $toolCalls, string $responseJson) : void {
        /** @var FunctionCall $currentFunctionCall */
        $currentFunctionCall = $toolCalls[count($toolCalls) - 1];
        $currentFunctionCall->functionArguments = $responseJson;
        $this->eventDispatcher->dispatch(new StreamedFunctionCallUpdated($currentFunctionCall));
    }
}