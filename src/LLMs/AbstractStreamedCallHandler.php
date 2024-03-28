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
use Cognesy\Instructor\Events\LLM\StreamedResponseReceived;
use Cognesy\Instructor\Exceptions\JSONParsingException;
use Cognesy\Instructor\Utils\Json;
use Cognesy\Instructor\Utils\Result;
use Generator;

abstract class AbstractStreamedCallHandler
{
    use ValidatesPartialResponse;

    protected EventDispatcher $events;
    protected ResponseModel $responseModel;
    protected array $request;
    protected PartialApiResponse $lastPartialResponse;
    protected string $responseJson = '';
    protected string $responseText = '';

    private bool $matchToExpectedFields = false;
    private bool $preventJsonSchema = false;

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
            finishReason: $this->lastPartialResponse->finishReason ?? '',
            rawResponse: $this->lastPartialResponse->responseData ?? [],
            isComplete: true,
        );
        $this->events->dispatch(new StreamedResponseFinished($llmResponse));
        return Result::success($llmResponse);
    }

    protected function processStream(Generator $stream) : Result {
        // process stream
        $functionCalls = [];
        $functionCalls[] = $this->newFunctionCall();
        /** @var PartialApiResponse $partialResponse */
        foreach($stream as $partialResponse){
            // receive data
            $this->events->dispatch(new StreamedResponseReceived($partialResponse));
            // store for finalization when we leave the loop
            $this->lastPartialResponse = $partialResponse;
            // situation 1: new function call
            $maybeFunctionName = $partialResponse->functionName;
            // create next FC only if JSON buffer is not empty (which is the case for 1st iteration)
            if ($maybeFunctionName && $this->responseJson) {
                $this->finalizeFunctionCall($functionCalls, $this->responseJson);
                $this->responseJson = ''; // reset json buffer
            }
            // situation 2: new delta
            // skip if no new delta
            $maybeArgumentChunk = $partialResponse->delta;
            if (!empty($maybeArgumentChunk)) {
                $this->events->dispatch(new ChunkReceived($maybeArgumentChunk));
                $this->responseText .= $maybeArgumentChunk;
                $this->responseJson = Json::findPartial($this->responseText);
                if (empty($this->responseJson)) {
                    continue;
                }
                $result = $this->validatePartialResponse($this->responseText, $this->responseModel, $this->preventJsonSchema, $this->matchToExpectedFields);
                if ($result->isFailure()) {
                    return $result;
                }
                $this->events->dispatch(new PartialJsonReceived($this->responseJson));
                $this->updateFunctionCall($functionCalls, $this->responseJson);
            }
        }
        // check if there are any toolCalls
        if (count($functionCalls) === 0) {
            return Result::failure(new RequestToLLMFailed($this->request, 'No tool calls found in the response'));
        }
        // finalize last function call
        if (count($functionCalls) > 0) {
            $this->finalizeFunctionCall($functionCalls, Json::find($this->responseText));
        }
        return Result::success($functionCalls);
    }

    protected function newFunctionCall(PartialApiResponse $response = null) : FunctionCall {
        $functionName = $response->functionName ?? $this->responseModel->functionName;
        $newFunctionCall = new FunctionCall(
            id: '',
            functionName: $functionName,
            functionArgsJson: ''
        );
        $this->events->dispatch(new StreamedFunctionCallStarted($newFunctionCall));
        return $newFunctionCall;
    }

    protected function updateFunctionCall(
        array $functionCalls,
        string $responseJson,
    ) : void {
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