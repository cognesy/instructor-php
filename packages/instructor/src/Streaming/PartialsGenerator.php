<?php declare(strict_types=1);

namespace Cognesy\Instructor\Streaming;

use Cognesy\Instructor\Contracts\CanGeneratePartials;
use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Events\PartialsGenerator\ChunkReceived;
use Cognesy\Instructor\Events\PartialsGenerator\PartialJsonReceived;
use Cognesy\Instructor\Events\PartialsGenerator\PartialResponseGenerated;
use Cognesy\Instructor\Events\PartialsGenerator\PartialResponseGenerationFailed;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedResponseFinished;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedResponseReceived;
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Instructor\Validation\PartialValidationPolicy;
use Cognesy\Polyglot\Inference\Collections\PartialInferenceResponseList;
use Cognesy\Polyglot\Inference\Creation\InferenceResponseFactory;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Generator;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;

/**
 * The PartialsGenerator class is responsible for generating
 * typed partial responses (instances of LLMPartialResponse)
 * from streamed data.
 */
class PartialsGenerator implements CanGeneratePartials
{

    // state
    private PartialJson $partialJson;
    private PartialObject $partialObject;
    private PartialInferenceResponseList $partialResponses;
    private ToolCallAssembler $toolCallAssembler;
    private SequenceableHandler $sequenceableHandler;
    private PartialValidationPolicy $validationPolicy;
    // options
    private bool $matchToExpectedFields = false;
    private bool $preventJsonSchema = false;

    public function __construct(
        private ResponseDeserializer $responseDeserializer,
        private ResponseTransformer $responseTransformer,
        private EventDispatcherInterface $events,
        ?PartialValidationPolicy $validationPolicy = null,
    ) {
        $this->toolCallAssembler = ToolCallAssembler::empty($events);
        $this->sequenceableHandler = new SequenceableHandler($events);
        $this->partialResponses = PartialInferenceResponseList::empty();
        $this->validationPolicy = $validationPolicy ?? new PartialValidationPolicy();
        // initialize typed properties to satisfy static analysis
        $this->partialJson = PartialJson::start();
        $this->partialObject = PartialObject::empty();
    }

    /**
     * Get generator of partial responses for the given stream and response model.
     *
     * @param Generator<PartialInferenceResponse> $stream
     * @param ResponseModel $responseModel
     * @return Generator<PartialInferenceResponse>
     */
    #[\Override]
    public function getPartialResponses(Generator $stream, ResponseModel $responseModel): Generator {
        $this->resetPartialResponse();

        /** @var PartialInferenceResponse $partialResponse */
        foreach ($stream as $partialResponse) {
            $this->onPartialReceived($partialResponse);
            $this->handleToolSignalIfAny($partialResponse->toolName, $responseModel);

            $delta = $partialResponse->contentDelta;
            if ($this->shouldSkipDelta($delta)) {
                continue;
            }

            $this->dispatchChunkReceived($delta);
            if (!$this->assembleAndHasJson($delta)) {
                continue;
            }

            $this->ensureToolCallStarted($responseModel->toolName());
            $this->updateToolCallArgs($responseModel->toolName());

            $emittable = $this->tryUpdatePartialObject($responseModel);
            if ($emittable === null) {
                continue;
            }

            yield $partialResponse
                ->withValue($emittable)
                ->withContent($this->partialJson->raw());
        }
        $lastPartial = $this->lastPartialResponse();
        $this->events->dispatch(new StreamedResponseFinished(['partial' => $lastPartial?->toArray() ?? []]));

        // finalize last function call
        // check if there are any toolCalls
        if ($this->toolCallAssembler->toolCalls()->count() === 0) {
            throw new RuntimeException('No tool calls found in the response');
        }

        // finalize last function call
        if ($this->toolCallAssembler->toolCalls()->count() > 0) {
            $this->toolCallAssembler = $this->toolCallAssembler->finalize(
                $responseModel->toolName(),
                $this->partialJson->finalized(),
            );
        }
        // finalize sequenceable
        $this->sequenceableHandler->finalize();
    }

    // INTERNAL ////////////////////////////////////////////////////

    private function onPartialReceived(PartialInferenceResponse $partialResponse): void {
        $this->events->dispatch(new StreamedResponseReceived(['partial' => $partialResponse->toArray()]));
        $this->partialResponses = $this->partialResponses->withNewPartialResponse($partialResponse);
    }

    private function handleToolSignalIfAny(?string $maybeToolName, ResponseModel $responseModel): void {
        if (!$maybeToolName) {
            return;
        }
        $buffered = $this->partialJson->isEmpty() ? null : $this->partialJson->normalized();
        $outcome = $this->toolCallAssembler->handleSignal($maybeToolName, $buffered, $responseModel->toolName());
        $this->toolCallAssembler = $outcome->assembler();
        if ($outcome->requiresBufferReset()) {
            $this->partialJson = PartialJson::start();
        }
    }

    private function shouldSkipDelta(?string $delta): bool {
        return empty($delta);
    }

    private function dispatchChunkReceived(string $delta): void {
        $this->events->dispatch(new ChunkReceived(['chunk' => $delta]));
    }

    private function assembleAndHasJson(string $delta): bool {
        $this->partialJson = $this->partialJson->assemble($delta);
        return !$this->partialJson->isEmpty();
    }

    private function ensureToolCallStarted(string $toolName): void {
        $this->toolCallAssembler = $this->toolCallAssembler->startIfEmpty($toolName);
    }

    private function updateToolCallArgs(string $toolName): void {
        $this->toolCallAssembler = $this->toolCallAssembler->update($toolName, $this->partialJson->normalized());
    }

    private function tryUpdatePartialObject(ResponseModel $responseModel): mixed {
        $update = $this->partialObject->update(
            partialJson: $this->partialJson,
            responseModel: $responseModel,
            deserializer: $this->responseDeserializer,
            transformer: $this->responseTransformer,
            validation: $this->validationPolicy,
            toolName: $responseModel->toolName(),
            preventJsonSchema: $this->preventJsonSchema,
            matchToExpectedFields: $this->matchToExpectedFields,
        );

        $result = $update->result();
        if ($result->isFailure()) {
            $this->events->dispatch(new PartialResponseGenerationFailed(['error' => (string)$result->error()]));
            return null;
        }
        $this->events->dispatch(new PartialJsonReceived(['partialJson' => $this->partialJson->normalized()]));
        $this->partialObject = $update->state();

        $emittable = $update->emittable();
        if ($emittable !== null) {
            $this->events->dispatch(new PartialResponseGenerated($emittable));
            if (($emittable instanceof Sequenceable)) {
                $this->sequenceableHandler->update($emittable);
            }
        }
        return $emittable;
    }

    public function getCompleteResponse(): InferenceResponse {
        return InferenceResponseFactory::fromPartialResponses($this->partialResponses);
    }

    public function lastPartialResponse(): ?PartialInferenceResponse {
        if ($this->partialResponses->isEmpty()) {
            return null;
        }
        return $this->partialResponses->last();
    }

    public function partialResponses(): PartialInferenceResponseList {
        return $this->partialResponses;
    }

    private function resetPartialResponse(): void {
        $this->partialJson = PartialJson::start();
        $this->partialObject = PartialObject::empty();
        $this->sequenceableHandler = new SequenceableHandler($this->events);
        $this->toolCallAssembler = ToolCallAssembler::empty($this->events);
        $this->partialResponses = PartialInferenceResponseList::empty();
    }
}
