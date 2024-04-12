<?php

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\ApiClient\Data\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Data\Responses\PartialApiResponse;
use Cognesy\Instructor\Contracts\CanGeneratePartials;
use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Instructor\Core\Response\ResponseDeserializer;
use Cognesy\Instructor\Core\Response\ResponseTransformer;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Data\ToolCall;
use Cognesy\Instructor\Data\ToolCalls;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\LLM\ChunkReceived;
use Cognesy\Instructor\Events\LLM\PartialJsonReceived;
use Cognesy\Instructor\Events\LLM\StreamedResponseReceived;
use Cognesy\Instructor\Events\LLM\StreamedToolCallCompleted;
use Cognesy\Instructor\Events\LLM\StreamedToolCallStarted;
use Cognesy\Instructor\Events\LLM\StreamedToolCallUpdated;
use Cognesy\Instructor\Events\RequestHandler\PartialResponseGenerated;
use Cognesy\Instructor\Events\RequestHandler\PartialResponseGenerationFailed;
use Cognesy\Instructor\Utils\Arrays;
use Cognesy\Instructor\Utils\Chain;
use Cognesy\Instructor\Utils\Json;
use Cognesy\Instructor\Utils\Result;
use Exception;
use Generator;

class PartialsGenerator implements CanGeneratePartials
{
    use ValidatesPartialResponse;

    // state
    private string $responseJson = '';
    private string $responseText = '';
    private string $previousHash = '';
    private PartialApiResponse $lastPartialResponse;
    private ToolCalls $toolCalls;
    private SequenceableHandler $sequenceableHandler;
    // options
    private bool $matchToExpectedFields = false;
    private bool $preventJsonSchema = false;

    public function __construct(
        private EventDispatcher $events,
        private ResponseDeserializer $responseDeserializer,
        private ResponseTransformer $responseTransformer,
    ) {
        $this->toolCalls = new ToolCalls();
        $this->sequenceableHandler = new SequenceableHandler($events);
    }

    public function getPartialResponses(Generator $stream, ResponseModel $responseModel, array $messages = []) : Iterable {
        // receive data
        /** @var PartialApiResponse $partialResponse */
        foreach($stream as $partialResponse) {
            $this->events->dispatch(new StreamedResponseReceived($partialResponse));
            // store for finalization when we leave the loop
            $this->lastPartialResponse = $partialResponse;

            // situation 1: new function call
            $maybeFunctionName = $partialResponse->functionName;
            // create next FC only if JSON buffer is not empty (which is the case for 1st iteration)
            if ($maybeFunctionName) {
                if (empty($this->responseJson)) {
                    $this->newToolCall($response->functionName ?? $responseModel->functionName);
                } else {
                    $this->finalizeToolCall($this->responseJson, $responseModel->functionName);
                    $this->responseJson = ''; // reset json buffer
                }
            }

            // situation 2: new delta
            $maybeArgumentChunk = $partialResponse->delta;
            if (empty($maybeArgumentChunk)) {
                // skip if no new delta
                continue;
            }
            $this->events->dispatch(new ChunkReceived($maybeArgumentChunk));
            $this->responseText .= $maybeArgumentChunk;
            $this->responseJson = Json::findPartial($this->responseText);
            if (empty($this->responseJson)) {
                continue;
            }
            if ($this->toolCalls->empty()) {
                $this->newToolCall($responseModel->functionName);
            }
            $result = $this->handleDelta($this->responseJson, $responseModel);
            if ($result->isFailure()) {
                continue;
            }
            $this->events->dispatch(new PartialJsonReceived($this->responseJson));
            yield $result->unwrap();
        }

        // finalize last function call
        // check if there are any toolCalls
        if ($this->toolCalls->count() === 0) {
            throw new Exception('No tool calls found in the response');
        }
        // finalize last function call
        if ($this->toolCalls->count() > 0) {
            $this->finalizeToolCall(Json::find($this->responseText), $responseModel->functionName);
        }
        // finalize sequenceable
        $this->sequenceableHandler->finalize();
    }

    protected function handleDelta(string $partialJson, ResponseModel $responseModel) : Result {
        return Chain::make()
            ->through(fn() => $this->validatePartialResponse($partialJson, $responseModel, $this->preventJsonSchema, $this->matchToExpectedFields))
            ->tap(fn() => $this->events->dispatch(new PartialJsonReceived($partialJson)))
            ->tap(fn() => $this->updateToolCall($partialJson, $responseModel->functionName))
            ->through(fn() => $this->tryGetPartialObject($partialJson, $responseModel))
            ->onFailure(fn($result) => $this->events->dispatch(
                new PartialResponseGenerationFailed(Arrays::toArray($result->error()))
            ))
            ->then(fn($result) => $this->getChangedOnly($result));
    }

    protected function tryGetPartialObject(
        string $partialJsonData,
        ResponseModel $responseModel,
    ) : Result {
        return Chain::from(fn() => Json::fix($partialJsonData))
            ->through(fn($jsonData) => $this->responseDeserializer->deserialize($jsonData, $responseModel))
            ->through(fn($object) => $this->responseTransformer->transform($object))
            ->result();
    }

    protected function getChangedOnly(Result $result) : ?Result {
        if ($result->isFailure()) {
            return $result;
        }
        $partialObject = $result->unwrap();
        // we only want to send partial response if it's different from the previous one
        $currentHash = hash('xxh3', Json::encode($partialObject));
        if ($this->previousHash == $currentHash) {
            return Result::failure('No changes detected');
        }
        $this->events->dispatch(new PartialResponseGenerated($partialObject));
        if (($partialObject instanceof Sequenceable)) {
            $this->sequenceableHandler->update($partialObject);
        }
        $this->previousHash = $currentHash;
        return $result;
    }

    public function resetPartialResponse() : void {
        $this->previousHash = '';
        $this->sequenceableHandler->reset();
    }

    public function getCompleteResponse() : ApiResponse {
        return new ApiResponse(
            content: $this->responseText,
            responseData: $this->lastPartialResponse->responseData ?? [],
            finishReason: $this->lastPartialResponse->finishReason ?? '',
            toolCalls: $this->toolCalls->all(),
        );
    }

    protected function newToolCall(string $name) : ToolCall {
        $newToolCall = $this->toolCalls->create($name);
        $this->events->dispatch(new StreamedToolCallStarted($newToolCall));
        return $newToolCall;
    }

    protected function updateToolCall(string $responseJson, string $defaultName) : ToolCall {
        $updatedToolCall = $this->toolCalls->updateLast($responseJson, $defaultName);
        $this->events->dispatch(new StreamedToolCallUpdated($updatedToolCall));
        return $updatedToolCall;
    }

    protected function finalizeToolCall(string $responseJson, string $defaultName) : ToolCall {
        $finalizedToolCall = $this->toolCalls->finalizeLast($responseJson, $defaultName);
        $this->events->dispatch(new StreamedToolCallCompleted($finalizedToolCall));
        return $finalizedToolCall;
    }
}