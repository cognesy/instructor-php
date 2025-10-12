<?php declare(strict_types=1);

namespace Cognesy\Instructor\Streaming\PartialGen;

use Cognesy\Instructor\Config\PartialsGeneratorConfig;
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
use Cognesy\Instructor\Streaming\SequenceGen\SequenceableEmitter;
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Generator;
use Psr\EventDispatcher\EventDispatcherInterface;

class GeneratePartialsFromJson implements CanGeneratePartials
{
    private AssemblePartialObject $partialAssembler;
    private readonly PartialsGeneratorConfig $config;

    public function __construct(
        private ResponseDeserializer $responseDeserializer,
        private ResponseTransformer $responseTransformer,
        private EventDispatcherInterface $events,
        ?PartialsGeneratorConfig $config = null,
    ) {
        $this->config = $config ?? new PartialsGeneratorConfig();
        $this->partialAssembler = new AssemblePartialObject(
            deserializer: $this->responseDeserializer,
            transformer: $this->responseTransformer,
            config: $this->config,
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