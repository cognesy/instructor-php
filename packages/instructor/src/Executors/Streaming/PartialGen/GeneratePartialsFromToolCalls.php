<?php declare(strict_types=1);

namespace Cognesy\Instructor\Executors\Streaming\PartialGen;

use Cognesy\Instructor\Contracts\CanGeneratePartials;
use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Events\PartialsGenerator\ChunkReceived;
use Cognesy\Instructor\Events\PartialsGenerator\PartialResponseGenerated;
use Cognesy\Instructor\Events\PartialsGenerator\PartialResponseGenerationFailed;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedResponseFinished;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedResponseReceived;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallCompleted;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallStarted;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallUpdated;
use Cognesy\Instructor\Executors\Streaming\SequenceGen\SequenceableEmitter;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Instructor\Validation\Contracts\CanValidatePartialResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Generator;
use Psr\EventDispatcher\EventDispatcherInterface;

class GeneratePartialsFromToolCalls implements CanGeneratePartials
{
    private AssemblePartialObject $partialAssembler;

    public function __construct(
        private CanDeserializeResponse $responseDeserializer,
        private CanValidatePartialResponse $validateResponse,
        private CanTransformResponse $responseTransformer,
        private EventDispatcherInterface $events,
    ) {
        $this->partialAssembler = new AssemblePartialObject(
            deserializer: $this->responseDeserializer,
            validator: $this->validateResponse,
            transformer: $this->responseTransformer,
        );
    }

    /**
     * Get generator of partial responses for the given stream and response model.
     *
     * @param Generator<PartialInferenceResponse> $stream
     * @param ResponseModel $responseModel
     * @return Generator<PartialInferenceResponse>
     */
    #[\Override]
    public function makePartialResponses(Generator $stream, ResponseModel $responseModel): Generator {
        $sequenceableHandler = new SequenceableEmitter($this->events);
        $toolName = $responseModel->toolName();
        $toolCallStreamState = new ToolCallStreamState(
            onStart: $this->dispatchStarted(...),
            onUpdate: $this->dispatchUpdated(...),
            onComplete: $this->dispatchCompleted(...),
        );
        $partialObject = PartialObject::empty();
        $lastPartial = null;

        /** @var PartialInferenceResponse $partialResponse */
        foreach ($stream as $partialResponse) {
            $this->events->dispatch(new StreamedResponseReceived(['partial' => $partialResponse->toArray()]));
            $lastPartial = $partialResponse;

            if ($partialResponse->toolName !== '') {
                $toolCallStreamState = $toolCallStreamState->handleSignal($partialResponse->toolName);
            }
            $argsDelta = $partialResponse->toolArgs !== '' ? $partialResponse->toolArgs : $partialResponse->contentDelta;
            if ($argsDelta === '') {
                continue;
            }
            $this->events->dispatch(new ChunkReceived(['chunk' => $argsDelta]));
            $toolCallStreamState = $toolCallStreamState->startIfEmpty($toolName);
            $toolCallStreamState = $toolCallStreamState->appendArgsDelta($argsDelta);
            $normalized = $toolCallStreamState->normalizedArgs();
            if ($normalized === '') {
                continue;
            }
            $partialObject = $this->tryUpdatePartialObject(
                responseModel: $responseModel,
                partialObject: $partialObject,
                partialJson: new PartialJson($normalized, $normalized)
            );
            $emittable = $partialObject->emittable();
            if ($emittable !== null) {
                $this->events->dispatch(new PartialResponseGenerated($emittable));
                if ($emittable instanceof Sequenceable) {
                    $sequenceableHandler->update($emittable);
                }
            }
            yield $partialResponse
                ->withValue($emittable ?? null)
                ->withContent($toolCallStreamState->rawArgs());
        }
        $this->events->dispatch(new StreamedResponseFinished(['partial' => $lastPartial?->toArray() ?? []]));

        // finalize tools mode: complete last call and update sequenceables with finalized args
        if ($toolCallStreamState->hasActive()) {
            $finalized = $toolCallStreamState->finalizedArgs();
            $toolCallStreamState->finalizeActive();
            if ($finalized !== '') {
                $partialObject = $this->tryUpdatePartialObject(
                    responseModel: $responseModel,
                    partialObject: $partialObject,
                    partialJson: new PartialJson($finalized, $finalized)
                );
                $emittable = $partialObject->emittable();
                if ($emittable instanceof Sequenceable) {
                    $sequenceableHandler->update($emittable);
                }
            }
        }

        // finalize sequenceable
        $sequenceableHandler->finalize();
    }

    private function tryUpdatePartialObject(
        ResponseModel $responseModel,
        PartialObject $partialObject,
        PartialJson $partialJson,
    ): PartialObject {
        $newPartialObject = $this->partialAssembler->makeWith(
            state: $partialObject,
            partialJson: $partialJson,
            responseModel: $responseModel,
        );
        $result = $newPartialObject->result();
        if ($result->isFailure()) {
            $this->events->dispatch(new PartialResponseGenerationFailed(['error' => (string) $result->error()]));
            return $newPartialObject->withEmittable(null);
        }
        return $newPartialObject;
    }

    private function dispatchStarted(ToolCall $toolCall): void {
        $this->events->dispatch(new StreamedToolCallStarted(['toolCall' => $toolCall->toArray()]));
    }

    private function dispatchUpdated(ToolCall $toolCall): void {
        $this->events->dispatch(new StreamedToolCallUpdated(['toolCall' => $toolCall->toArray()]));
    }

    private function dispatchCompleted(ToolCall $toolCall): void {
        $this->events->dispatch(new StreamedToolCallCompleted(['toolCall' => $toolCall->toArray()]));
    }
}