<?php

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanGeneratePartials;
use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Instructor\Core\Traits\ValidatesPartialResponse;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Events\PartialsGenerator\ChunkReceived;
use Cognesy\Instructor\Events\PartialsGenerator\PartialJsonReceived;
use Cognesy\Instructor\Events\PartialsGenerator\PartialResponseGenerated;
use Cognesy\Instructor\Events\PartialsGenerator\PartialResponseGenerationFailed;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedResponseFinished;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedResponseReceived;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallCompleted;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallStarted;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallUpdated;
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Data\ToolCalls;
use Cognesy\Utils\Arrays;
use Cognesy\Utils\Chain\ResultChain;
use Cognesy\Utils\Json\Json;
use Cognesy\Utils\Result\Result;
use Exception;
use Generator;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The PartialsGenerator class is responsible for generating
 * typed partial responses (instances of LLMPartialResponse)
 * from streamed data.
 */
class PartialsGenerator implements CanGeneratePartials
{
    use ValidatesPartialResponse;

    // state
    private string $responseJson = '';
    private string $responseText = '';
    private string $previousHash = '';
    private array $partialResponses = [];
    private ToolCalls $toolCalls;
    private SequenceableHandler $sequenceableHandler;
    // options
    private bool $matchToExpectedFields = false;
    private bool $preventJsonSchema = false;

    public function __construct(
        private ResponseDeserializer $responseDeserializer,
        private ResponseTransformer $responseTransformer,
        private EventDispatcherInterface $events,
    ) {
        $this->toolCalls = new ToolCalls();
        $this->sequenceableHandler = new SequenceableHandler($events);
    }

    public function resetPartialResponse() : void {
        $this->previousHash = '';
        $this->responseText = '';
        $this->responseJson = '';
        $this->sequenceableHandler->reset();
        $this->toolCalls->reset();
    }

    /**
     * Get generator of partial responses for the given stream and response model.
     *
     * @param Generator<PartialInferenceResponse> $stream
     * @param ResponseModel $responseModel
     * @return Generator<PartialInferenceResponse>
     */
    public function getPartialResponses(Generator $stream, ResponseModel $responseModel) : Generator {
        // reset state
        $this->resetPartialResponse();

        // receive data
        /** @var PartialInferenceResponse $partialResponse */
        foreach($stream as $partialResponse) {
            $this->events->dispatch(new StreamedResponseReceived(['partial' => $partialResponse->toArray()]));
            // store partial response
            $this->partialResponses[] = $partialResponse;

            // situation 1: new function call
            $maybeToolName = $partialResponse->toolName;
            // create next FC only if JSON buffer is not empty (which is the case for 1st iteration)
            if ($maybeToolName) {
                if (empty($this->responseJson)) {
                    $this->newToolCall($response->toolName ?? $responseModel->toolName());
                } else {
                    $this->finalizeToolCall($this->responseJson, $responseModel->toolName());
                    $this->responseJson = ''; // reset json buffer
                }
            }

            // situation 2: new delta
            $maybeArgumentChunk = $partialResponse->contentDelta;
            if (empty($maybeArgumentChunk)) {
                continue;
            }

            $this->events->dispatch(new ChunkReceived(['chunk' => $maybeArgumentChunk]));
            $this->responseText .= $maybeArgumentChunk;
            $this->responseJson = Json::fromPartial($this->responseText)->toString();
            if (empty($this->responseJson)) {
                continue;
            }
            if ($this->toolCalls->empty()) {
                $this->newToolCall($responseModel->toolName());
            }
            $result = $this->handleDelta($this->responseJson, $responseModel);
            if ($result->isFailure()) {
                continue;
            }
            $this->events->dispatch(new PartialJsonReceived(['partialJson' => $this->responseJson]));

            yield $partialResponse
                ->withValue($result->unwrap())
                ->withContent($this->responseText);
        }
        $this->events->dispatch(new StreamedResponseFinished(['partial' => $this->lastPartialResponse()->toArray()]));

        // finalize last function call
        // check if there are any toolCalls
        if ($this->toolCalls->count() === 0) {
            throw new Exception('No tool calls found in the response');
        }
        // finalize last function call
        if ($this->toolCalls->count() > 0) {
            $this->finalizeToolCall(Json::fromString($this->responseText)->toString(), $responseModel->toolName());
        }
        // finalize sequenceable
        $this->sequenceableHandler->finalize();
    }

    public function getCompleteResponse() : InferenceResponse {
        return InferenceResponse::fromPartialResponses($this->partialResponses);
    }

    public function lastPartialResponse() : PartialInferenceResponse {
        if (empty($this->partialResponses)) {
            throw new Exception('No partial responses found');
        }
        $index = count($this->partialResponses) - 1;
        return $this->partialResponses[$index];
    }

    public function partialResponses() : array {
        return $this->partialResponses;
    }

    // INTERNAL ////////////////////////////////////////////////////////

    protected function handleDelta(
        string $partialJson,
        ResponseModel $responseModel
    ) : Result {
        return ResultChain::make()
            ->through(fn() => $this->validatePartialResponse($partialJson, $responseModel, $this->preventJsonSchema, $this->matchToExpectedFields))
            ->tap(fn() => $this->events->dispatch(new PartialJsonReceived(['partialJson' => $partialJson])))
            ->tap(fn() => $this->updateToolCall($partialJson, $responseModel->toolName()))
            ->through(fn() => $this->tryGetPartialObject($partialJson, $responseModel))
            ->onFailure(fn($result) => $this->events->dispatch(
                new PartialResponseGenerationFailed(Arrays::asArray($result->error()))
            ))
            ->then(fn($result) => $this->getChangedOnly($result))
            ->result();
    }

    protected function tryGetPartialObject(
        string $partialJsonData,
        ResponseModel $responseModel,
    ) : Result {
        return ResultChain::from(fn() => Json::fromPartial($partialJsonData)->toString())
            ->through(fn($json) => $this->responseDeserializer->deserialize($json, $responseModel, $this->toolCalls->last()?->name()))
            ->through(fn($object) => $this->responseTransformer->transform($object))
            ->result();
    }

    protected function getChangedOnly(Result $result) : ?Result {
        if ($result->isFailure()) {
            return $result;
        }
        $partialObject = $result->unwrap();
        if ($partialObject === null) {
            return Result::failure('Null object returned');
        }

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

    protected function newToolCall(string $name) : ToolCall {
        $newToolCall = $this->toolCalls->add($name);
        $this->events->dispatch(new StreamedToolCallStarted(['toolCall' => $newToolCall->toArray()]));
        return $newToolCall;
    }

    protected function updateToolCall(string $responseJson, string $defaultName) : ToolCall {
        $updatedToolCall = $this->toolCalls->updateLast($responseJson, $defaultName);
        $this->events->dispatch(new StreamedToolCallUpdated(['toolCall' => $updatedToolCall->toArray()]));
        return $updatedToolCall;
    }

    protected function finalizeToolCall(string $responseJson, string $defaultName) : ToolCall {
        $finalizedToolCall = $this->toolCalls->finalizeLast($responseJson, $defaultName);
        $this->events->dispatch(new StreamedToolCallCompleted(['toolCall' => $finalizedToolCall->toArray()]));
        return $finalizedToolCall;
    }
}