<?php declare(strict_types=1);

namespace Cognesy\Instructor\Executors\Streaming\PartialGen;

use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Events\PartialsGenerator\ChunkReceived;
use Cognesy\Instructor\Events\PartialsGenerator\PartialJsonReceived;
use Cognesy\Instructor\Events\PartialsGenerator\PartialResponseGenerated;
use Cognesy\Instructor\Events\PartialsGenerator\PartialResponseGenerationFailed;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedResponseFinished;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedResponseReceived;
use Cognesy\Instructor\Executors\Streaming\Contracts\CanGeneratePartials;
use Cognesy\Instructor\Executors\Streaming\SequenceGen\SequenceableEmitter;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Instructor\Validation\Contracts\CanValidatePartialResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Generator;
use Psr\EventDispatcher\EventDispatcherInterface;

class GeneratePartialsFromJson implements CanGeneratePartials
{
    private AssemblePartialObject $partialAssembler;

    public function __construct(
        private CanDeserializeResponse $responseDeserializer,
        private CanValidatePartialResponse $partialValidator,
        private CanTransformResponse $responseTransformer,
        private EventDispatcherInterface $events,
    ) {
        $this->partialAssembler = new AssemblePartialObject(
            deserializer: $this->responseDeserializer,
            validator: $this->partialValidator,
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
        $partialJson = PartialJson::start();
        $partialObject = PartialObject::empty();
        $lastPartial = null;

        /** @var PartialInferenceResponse $partialResponse */
        foreach ($stream as $partialResponse) {
            $this->events->dispatch(new StreamedResponseReceived(['partial' => $partialResponse->toArray()]));
            $lastPartial = $partialResponse;

            // Pass-through for pre-valued partials (e.g., tests providing emittable snapshots)
            if ($partialResponse->hasValue()) {
                $emittable = $partialResponse->value();
                $this->events->dispatch(new PartialResponseGenerated($emittable));
                if ($emittable instanceof Sequenceable) {
                    $sequenceableHandler->update($emittable);
                }
                yield $partialResponse; // value already set upstream
                continue;
            }

            // Content mode
            $delta = $partialResponse->contentDelta;
            if ($delta === '') {
                continue;
            }
            $this->events->dispatch(new ChunkReceived(['chunk' => $delta]));
            $partialJson = $this->updatePartialJson($partialJson, $delta);
            if ($partialJson->isEmpty()) {
                continue;
            }
            $partialObject = $this->tryUpdatePartialObject(
                responseModel: $responseModel,
                partialObject: $partialObject,
                partialJson: $partialJson
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
                ->withContent($partialJson->raw());
        }
        $this->events->dispatch(new StreamedResponseFinished(['partial' => $lastPartial?->toArray() ?? []]));

        // finalize sequenceable
        $sequenceableHandler->finalize();
    }


    // INTERNAL ////////////////////////////////////////////////////

    private function updatePartialJson(PartialJson $partialJson, string $delta): PartialJson {
        $newPartialJson = $partialJson->assemble($delta);
        if (!$newPartialJson->equals($partialJson)) {
            $this->events->dispatch(new PartialJsonReceived(['partialJson' => $newPartialJson->normalized()]));
        }
        return $newPartialJson;
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
}
