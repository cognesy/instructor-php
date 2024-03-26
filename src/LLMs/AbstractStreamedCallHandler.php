<?php

namespace Cognesy\Instructor\LLMs;

use Cognesy\Instructor\ApiClient\Data\Responses\PartialApiResponse;
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

abstract class AbstractStreamedCallHandler
{
    protected EventDispatcher $events;
    protected ResponseModel $responseModel;
    protected array $request;
    protected PartialApiResponse $lastResponse;
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
        $result = $this->processStream($result->unwrap());
        if ($result->isFailure()) {
            return $result;
        }
        $llmResponse = new LLMResponse(
            functionCalls: $result->unwrap(),
            finishReason: $this->lastResponse->finishReason ?? '',
            rawResponse: $this->lastResponse->responseData ?? [],
            isComplete: true,
        );
        $this->events->dispatch(new StreamedResponseFinished($llmResponse));
        return Result::success($llmResponse);
    }

    protected function newFunctionCall(PartialApiResponse $response = null) : FunctionCall {
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

    abstract protected function getStream() : Result;
}