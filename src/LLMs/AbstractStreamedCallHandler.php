<?php

namespace Cognesy\Instructor\LLMs;

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
use Cognesy\Instructor\Utils\Result;

class AbstractStreamedCallHandler
{
    protected EventDispatcher $events;
    protected ResponseModel $responseModel;
    protected array $request;
    protected mixed $lastResponse;
    protected string $responseJson = '';

    /**
     * Handle streamed chat call
     */
    public function handle() : Result {
        $this->events->dispatch(new RequestSentToLLM($this->request));
        // get stream
        $result = $this->getStream();
        if ($result->isFailure()) {
            $this->events->dispatch(new RequestToLLMFailed($this->request, $result->errorMessage()));
            return $result;
        }
        // process stream
        $result = $this->processStream($result->value());
        if ($result->isFailure()) {
            return $result;
        }
        $llmResponse = new LLMResponse(
            functionCalls: $result->value(),
            finishReason: $this->getFinishReason($this->lastResponse),
            rawResponse: $this->lastResponse?->toArray(),
            isComplete: true,
        );
        $this->events->dispatch(new StreamedResponseFinished($llmResponse));
        return Result::success($llmResponse);
    }

    protected function newFunctionCall($response = null) : FunctionCall {
        $newFunctionCall = new FunctionCall(
            toolCallId: '',
            functionName: $this->responseModel->functionName, // ATTENTION!
            functionArgsJson: ''
        );
        $this->events->dispatch(new StreamedFunctionCallStarted($newFunctionCall));
        return $newFunctionCall;
    }

    protected function updateFunctionCall(
        array $functionCalls,
        string $responseJson,
        string $responseChunk,
    ) : void {
        $this->events->dispatch(new ChunkReceived($responseChunk));
        $this->events->dispatch(new PartialJsonReceived($responseJson));
        /** @var \Cognesy\Instructor\Data\FunctionCall $currentFunctionCall */
        $currentFunctionCall = $functionCalls[count($functionCalls) - 1];
        $currentFunctionCall->functionArgsJson = $responseJson;
        $this->events->dispatch(new StreamedFunctionCallUpdated($currentFunctionCall));
    }

    protected function finalizeFunctionCall(array $functionCalls, string $responseJson) : void {
        /** @var \Cognesy\Instructor\Data\FunctionCall $currentFunctionCall */
        $currentFunctionCall = $functionCalls[count($functionCalls) - 1];
        $currentFunctionCall->functionArgsJson = $responseJson;
        $this->events->dispatch(new StreamedFunctionCallCompleted($currentFunctionCall));
    }
}