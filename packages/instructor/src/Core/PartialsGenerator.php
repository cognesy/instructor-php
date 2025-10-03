<?php declare(strict_types=1);

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
use Cognesy\Pipeline\Enums\ErrorStrategy;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Polyglot\Inference\Collections\PartialInferenceResponseList;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\InferenceResponseFactory;
use Cognesy\Utils\Arrays;
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
    private PartialInferenceResponseList $partialResponses;
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
        $this->partialResponses = PartialInferenceResponseList::empty();
    }

    public function resetPartialResponse() : void {
        $this->previousHash = '';
        $this->responseText = '';
        $this->responseJson = '';
        $this->sequenceableHandler->reset();
        $this->toolCalls = ToolCalls::empty();
        $this->partialResponses = PartialInferenceResponseList::empty();
    }

    /**
     * Get generator of partial responses for the given stream and response model.
     *
     * @param Generator<PartialInferenceResponse> $stream
     * @param ResponseModel $responseModel
     * @return Generator<PartialInferenceResponse>
     */
    #[\Override]
    public function getPartialResponses(Generator $stream, ResponseModel $responseModel) : Generator {
        $this->resetPartialResponse();
        // receive data
        /** @var PartialInferenceResponse $partialResponse */
        foreach($stream as $partialResponse) {
            $this->events->dispatch(new StreamedResponseReceived(['partial' => $partialResponse->toArray()]));
            // store partial response
            $this->partialResponses = $this->partialResponses->withNewPartialResponse($partialResponse);

            // situation 1: new function call
            $this->handleToolSignal($partialResponse, $responseModel);

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
            if ($this->toolCalls->isEmpty()) {
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
        $lastPartial = $this->lastPartialResponse();
        $this->events->dispatch(new StreamedResponseFinished(['partial' => $lastPartial?->toArray() ?? []]));

        // finalize last function call
        // check if there are any toolCalls
        if ($this->toolCalls->count() === 0) {
            throw new Exception('No tool calls found in the response');
        }
        // finalize last function call
        if ($this->toolCalls->count() > 0) {
            $this->finalizeToolCall(
                $responseModel->toolName(),
                Json::fromString($this->responseText)->toString()
            );
        }
        // finalize sequenceable
        $this->sequenceableHandler->finalize();
    }

    public function getCompleteResponse() : InferenceResponse {
        return InferenceResponseFactory::fromPartialResponses($this->partialResponses);
    }

    public function lastPartialResponse() : ?PartialInferenceResponse {
        if ($this->partialResponses->isEmpty()) {
            return null;
        }
        return $this->partialResponses->last();
    }

    public function partialResponses() : PartialInferenceResponseList {
        return $this->partialResponses;
    }

    // INTERNAL ////////////////////////////////////////////////////////
    private function handleToolSignal(PartialInferenceResponse $partialResponse, ResponseModel $responseModel) : void {
        $maybeToolName = $partialResponse->toolName;
        if (!$maybeToolName) {
            return;
        }

        $active = $this->toolCalls->last();
        $hasBuffer = !empty($this->responseJson);

        // If a tool is already active, buffer is empty, and the same tool is signaled again, ignore duplicate start
        if ($active !== null && !$hasBuffer && ($active->name() === $maybeToolName)) {
            return;
        }

        // If we have buffered args, finalize the previous tool call first
        if ($hasBuffer) {
            $this->finalizeToolCall($responseModel->toolName(), $this->responseJson);
            $this->responseJson = '';
        }

        // Start the new (or first) tool call with the signaled name
        $this->newToolCall($maybeToolName);
    }

    protected function handleDelta(
        string $partialJson,
        ResponseModel $responseModel
    ) : Result {
        return $this->makeDeltaPipeline($responseModel)
            ->executeWith(ProcessingState::with($partialJson))
            ->result();
    }

    protected function tryGetPartialObject(
        string $partialJsonData,
        ResponseModel $responseModel,
    ) : Result {
        $pipeline = $this->makePartialDeserializationPipeline(
            responseModel: $responseModel,
            toolName: $this->toolCalls->last()?->name() ?? ''
        );
        $json = Json::fromPartial($partialJsonData)->toString();
        return $pipeline->executeWith(ProcessingState::with($json))->result();
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
        if ($this->previousHash === $currentHash) {
            return Result::failure('No changes detected');
        }
        $this->events->dispatch(new PartialResponseGenerated($partialObject));

        if (($partialObject instanceof Sequenceable)) {
            $this->sequenceableHandler->update($partialObject);
        }
        $this->previousHash = $currentHash;
        return $result;
    }

    protected function newToolCall(string $name) : void {
        $this->toolCalls = $this->toolCalls->withAddedToolCall($name);
        $newToolCall = $this->toolCalls->last();
        $this->events->dispatch(new StreamedToolCallStarted(['toolCall' => $newToolCall?->toArray()]));
    }

    protected function updateToolCall(string $name, string $responseJson) : void {
        $this->toolCalls = $this->toolCalls->withLastToolCallUpdated($name, $responseJson);
        $updatedToolCall = $this->toolCalls->last();
        $this->events->dispatch(new StreamedToolCallUpdated(['toolCall' => $updatedToolCall?->toArray()]));
    }

    protected function finalizeToolCall(string $name, string $responseJson) : void {
        $this->toolCalls = $this->toolCalls->withLastToolCallUpdated($name, $responseJson);
        $finalizedToolCall = $this->toolCalls->last();
        $this->events->dispatch(new StreamedToolCallCompleted(['toolCall' => $finalizedToolCall?->toArray()]));
    }

    private function makeDeltaPipeline(ResponseModel $responseModel) : Pipeline {
        return Pipeline::builder(ErrorStrategy::FailFast)
            ->through(fn($json) => $this->validatePartialResponse($json, $responseModel, $this->preventJsonSchema, $this->matchToExpectedFields))
            ->tap(function($json) : void {
                $this->events->dispatch(new PartialJsonReceived(['partialJson' => $json]));
            })
            ->tap(function($json) use ($responseModel) : void {
                $this->updateToolCall($responseModel->toolName(), $json);
            })
            ->through(fn($json) => $this->tryGetPartialObject($json, $responseModel))
            ->onFailure(function($result) : void {
                $this->events->dispatch(
                    new PartialResponseGenerationFailed(Arrays::asArray($result->exception()))
                );
            })
            ->finally(fn($state) => $this->getChangedOnly($state->result()))
            ->create();
    }

    private function makePartialDeserializationPipeline(ResponseModel $responseModel, string $toolName) : Pipeline {
        return Pipeline::builder(ErrorStrategy::FailFast)
            ->through(fn($json) => $this->responseDeserializer->deserialize($json, $responseModel, $toolName))
            ->through(fn($object) => $this->responseTransformer->transform($object))
            ->create();
    }
}
