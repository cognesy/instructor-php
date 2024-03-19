<?php

namespace Cognesy\Instructor\LLMs\OpenAI\ToolsMode;

use Cognesy\Instructor\Data\FunctionCall;
use Cognesy\Instructor\Data\LLMResponse;
use Cognesy\Instructor\Data\ResponseModel;
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
        $functionCalls = [];
        foreach($stream as $response){
            $this->eventDispatcher->dispatch(new StreamedResponseReceived($response->toArray()));
            $maybeFunctionName = $response->choices[0]->delta->toolCalls[0]->function->name ?? null;
            $maybeArgumentChunk = $response->choices[0]->delta->toolCalls[0]->function->arguments ?? null;

            // situation 1: new function call
            if ($maybeFunctionName) {
                if (count($functionCalls) > 0) {
                    $this->finalizeFunctionCall($functionCalls, $responseJson);
                    $responseJson = ''; // reset json buffer
                }
                // start capturing new function call
                $newFunctionCall = $this->newFunctionCall($response);
                $functionCalls[] = $newFunctionCall;
            }

            // situation 2: regular data chunk
            if ($maybeArgumentChunk) {
                $this->eventDispatcher->dispatch(new ChunkReceived($maybeArgumentChunk));
                $responseJson .= $maybeArgumentChunk;
                $this->eventDispatcher->dispatch(new PartialJsonReceived($responseJson));
                $this->updateFunctionCall($functionCalls, $responseJson);
            }

            // situation 3: finishReason other than 'stop'
            //$finishReason = $response->choices[0]->finishReason ?? null;
            //if ($finishReason) {
            //    $this->finalizeFunctionCall($toolCalls, $responseJson);
            //    return Result::success(new LLMResponse($toolCalls, $finishReason, $response->toArray(), true));
            //}
        }
        // check if there are any toolCalls
        if (count($functionCalls) === 0) {
            return Result::failure(new RequestToLLMFailed($this->request, ['No tool calls found in the response']));
        }
        // finalize last function call
        if (count($functionCalls) > 0) {
            $this->finalizeFunctionCall($functionCalls, $responseJson);
        }
        // handle finishReason other than 'stop'
        $response = new LLMResponse(
            functionCalls: $functionCalls,
            finishReason: ($response->choices[0]->finishReason ?? null),
            rawResponse: $response->toArray(),
            isComplete: true,
        );
        $this->eventDispatcher->dispatch(new StreamedResponseFinished($response));
        return Result::success($response);
    }

    private function newFunctionCall($response) : FunctionCall {
        $newFunctionCall = new FunctionCall(
            toolCallId: $response->choices[0]->delta->toolCalls[0]->id,
            functionName: $response->choices[0]->delta->toolCalls[0]->function->name,
            functionArgsJson: ''
        );
        $this->eventDispatcher->dispatch(new StreamedFunctionCallStarted($newFunctionCall));
        return $newFunctionCall;
    }

    private function finalizeFunctionCall(array $functionCalls, string $responseJson) : void {
        /** @var \Cognesy\Instructor\Data\FunctionCall $currentFunctionCall */
        $currentFunctionCall = $functionCalls[count($functionCalls) - 1];
        $currentFunctionCall->functionArgsJson = $responseJson;
        $this->eventDispatcher->dispatch(new StreamedFunctionCallCompleted($currentFunctionCall));
    }

    private function updateFunctionCall(array $functionCalls, string $responseJson) : void {
        /** @var \Cognesy\Instructor\Data\FunctionCall $currentFunctionCall */
        $currentFunctionCall = $functionCalls[count($functionCalls) - 1];
        $currentFunctionCall->functionArgsJson = $responseJson;
        $this->eventDispatcher->dispatch(new StreamedFunctionCallUpdated($currentFunctionCall));
    }
}