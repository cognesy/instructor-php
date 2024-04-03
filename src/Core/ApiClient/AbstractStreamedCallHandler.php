<?php

namespace Cognesy\Instructor\Core\ApiClient;

use Cognesy\Instructor\ApiClient\Data\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Data\Responses\PartialApiResponse;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Data\ToolCall;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\LLM\ChunkReceived;
use Cognesy\Instructor\Events\LLM\PartialJsonReceived;
use Cognesy\Instructor\Events\LLM\RequestSentToLLM;
use Cognesy\Instructor\Events\LLM\RequestToLLMFailed;
use Cognesy\Instructor\Events\LLM\StreamedResponseFinished;
use Cognesy\Instructor\Events\LLM\StreamedResponseReceived;
use Cognesy\Instructor\Events\LLM\StreamedToolCallCompleted;
use Cognesy\Instructor\Events\LLM\StreamedToolCallStarted;
use Cognesy\Instructor\Events\LLM\StreamedToolCallUpdated;
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
     * @return Result<ApiResponse, mixed>
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
        $toolCalls = $result->unwrap();
        $response = new ApiResponse(
            responseData: $this->lastPartialResponse->responseData ?? [],
            finishReason: $this->lastPartialResponse->finishReason ?? '',
            toolCalls: $toolCalls,
        );
        $this->events->dispatch(new StreamedResponseFinished($response));
        return Result::success($response);
    }

    protected function processStream(Iterable $stream) : Result {
        // process stream
        $toolCalls = [];
        $toolCalls[] = $this->newToolCall();
        /** @var PartialApiResponse $partialResponse */
        foreach($stream as $partialResponse) {
            // receive data
            $this->events->dispatch(new StreamedResponseReceived($partialResponse));
            // store for finalization when we leave the loop
            $this->lastPartialResponse = $partialResponse;
            // situation 1: new function call
            $maybeFunctionName = $partialResponse->functionName;
            // create next FC only if JSON buffer is not empty (which is the case for 1st iteration)
            if ($maybeFunctionName && $this->responseJson) {
                $this->finalizeToolCall($toolCalls, $this->responseJson);
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
                $this->updateToolCall($toolCalls, $this->responseJson);
            }
        }
        // check if there are any tool calls
        if (count($toolCalls) === 0) {
            return Result::failure(new RequestToLLMFailed($this->request, 'No tool calls found in the response'));
        }
        // finalize last tool call
        if (count($toolCalls) > 0) {
            $this->finalizeToolCall($toolCalls, Json::find($this->responseText));
        }
        return Result::success($toolCalls);
    }

    protected function newToolCall(PartialApiResponse $response = null) : ToolCall {
        $toolName = $response->functionName ?? $this->responseModel->functionName;
        $newToolCall = new ToolCall(
            name: $toolName,
            args: ''
        );
        $this->events->dispatch(new StreamedToolCallStarted($newToolCall));
        return $newToolCall;
    }

    protected function updateToolCall(
        array  $toolCalls,
        string $responseJson,
    ) : void {
        /** @var \Cognesy\Instructor\Data\ToolCall $currentToolCall */
        $currentToolCall = $toolCalls[count($toolCalls) - 1];
        $currentToolCall->args = $responseJson;
        $this->events->dispatch(new StreamedToolCallUpdated($currentToolCall));
    }

    protected function finalizeToolCall(array $toolCalls, string $responseJson) : void {
        /** @var \Cognesy\Instructor\Data\ToolCall $currentToolCall */
        $currentToolCall = $toolCalls[count($toolCalls) - 1];
        $currentToolCall->args = $responseJson;
        $this->events->dispatch(new StreamedToolCallCompleted($currentToolCall));
    }

    abstract protected function getStream() : Result;
}