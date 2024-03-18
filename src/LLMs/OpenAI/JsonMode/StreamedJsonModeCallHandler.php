<?php
namespace Cognesy\Instructor\LLMs\OpenAI\JsonMode;

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
use Cognesy\Instructor\Utils\Json;
use Cognesy\Instructor\Utils\Result;
use Exception;
use OpenAI\Client;
use OpenAI\Responses\Chat\CreateResponse;
use OpenAI\Responses\Chat\CreateStreamedResponseDelta;

class StreamedJsonModeCallHandler
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
        $toolCalls = [];
        $newFunctionCall = $this->newFunctionCall();
        $toolCalls[] = $newFunctionCall;
        $responseJson = '';
        $lastResponse = null;
        foreach($stream as $response){
            $lastResponse = $response;
            $this->eventDispatcher->dispatch(new StreamedResponseReceived($response->toArray()));
            $maybeArgumentChunk = $response->choices[0]->delta->content ?? null;

            if ($maybeArgumentChunk === null) {
                continue;
            }
            $this->eventDispatcher->dispatch(new ChunkReceived($maybeArgumentChunk));
            $responseJson .= $maybeArgumentChunk;
            $this->eventDispatcher->dispatch(new PartialJsonReceived(Json::extractPartial($responseJson)));
            $this->updateFunctionCall($toolCalls, $responseJson);
        }
        // check if there are any toolCalls
        if (count($toolCalls) === 0) {
            return Result::failure(new RequestToLLMFailed($this->request, ['No tool calls found in the response']));
        }
        // finalize last function call
        if (count($toolCalls) > 0) {
            $this->finalizeFunctionCall($toolCalls, Json::extract($responseJson));
        }
        // handle finishReason other than 'stop'
        $llmResponse = new LLMResponse(
            toolCalls: $toolCalls,
            finishReason: ($lastResponse->choices[0]->finishReason ?? ''),
            rawData: $lastResponse?->toArray(),
            isComplete: true,
        );
        $this->eventDispatcher->dispatch(new StreamedResponseFinished($llmResponse));
        return Result::success($llmResponse);
    }

    private function newFunctionCall() : FunctionCall {
        $newFunctionCall = new FunctionCall(
            toolCallId: '',
            functionName: $this->responseModel->functionName,
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