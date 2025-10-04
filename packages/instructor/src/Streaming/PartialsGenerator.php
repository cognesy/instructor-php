<?php declare(strict_types=1);

namespace Cognesy\Instructor\Streaming;

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
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Generator;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The PartialsGenerator class is responsible for generating
 * typed partial responses (instances of LLMPartialResponse)
 * from streamed data.
 */
class PartialsGenerator implements CanGeneratePartials
{
    // state
    private PartialObjectAssembler $partialAssembler;
    // options
    private readonly PartialsGeneratorConfig $config;

    public function __construct(
        private ResponseDeserializer $responseDeserializer,
        private ResponseTransformer $responseTransformer,
        private EventDispatcherInterface $events,

        ?PartialsGeneratorConfig $config = null,
    ) {
        $this->config = $config ?? new PartialsGeneratorConfig();
        $this->partialAssembler = new PartialObjectAssembler(
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
        $sequenceableHandler = new SequenceableHandler($this->events);
        $toolName = $responseModel->toolName();
        $toolCallStreamState = ToolCallStreamState::empty($this->events);
        $partialJson = PartialJson::start();
        $partialObject = PartialObject::empty();
        $lastPartial = null;

        /** @var PartialInferenceResponse $partialResponse */
        foreach ($stream as $partialResponse) {
            $this->events->dispatch(new StreamedResponseReceived(['partial' => $partialResponse->toArray()]));
            $lastPartial = $partialResponse;
            $toolCallStreamState = $this->handleToolSignalIfAny(
                $toolCallStreamState,
                $partialJson,
                $partialResponse->toolName,
                $toolName
            );
            if ($toolCallStreamState->requiresBufferReset()) {
                $partialJson = PartialJson::start();
            }

            $delta = $partialResponse->contentDelta;
            if (empty($delta)) {
                continue;
            }

            $this->events->dispatch(new ChunkReceived(['chunk' => $delta]));
            $partialJson = $this->updatePartialJson($partialJson, $delta);
            if ($partialJson->isEmpty()) {
                continue;
            }

            $toolCallStreamState = $toolCallStreamState->startIfEmpty($toolName);
            $toolCallStreamState = $toolCallStreamState->update($toolName, $partialJson->normalized());

            $partialObject = $this->tryUpdatePartialObject(
                responseModel: $responseModel,
                partialObject: $partialObject,
                partialJson: $partialJson
            );

            // Emit event and update sequence only when a new emittable object is available
            $emittable = $partialObject->emittable();
            if ($emittable !== null) {
                $this->events->dispatch(new PartialResponseGenerated($emittable));
                if (($emittable instanceof Sequenceable)) {
                    $sequenceableHandler->update($emittable);
                }
            }

            // Always yield partials so content deltas and usage aggregate correctly
            yield $partialResponse
                ->withValue($emittable)
                ->withContent($partialJson->raw());
        }
        $this->events->dispatch(new StreamedResponseFinished(['partial' => $lastPartial?->toArray() ?? []]));

        // finalize last function call
        if ($toolCallStreamState->toolCalls()->count() > 0) {
            // update sequenceable if needed
            $toolCallStreamState = $toolCallStreamState->finalize(
                $responseModel->toolName(),
                $partialJson->finalized(),
            );
            $finalPartialJson = new PartialJson($partialJson->finalized(), $partialJson->finalized());
            $partialObject = $this->tryUpdatePartialObject(
                responseModel: $responseModel,
                partialObject: $partialObject,
                partialJson: $finalPartialJson
            );
            $emittable = $partialObject->emittable();
                if ($emittable instanceof Sequenceable) {
                    $sequenceableHandler->update($emittable);
                }
        }

        // finalize sequenceable
        $sequenceableHandler->finalize();
    }

    // INTERNAL ////////////////////////////////////////////////////

    private function handleToolSignalIfAny(
        ToolCallStreamState $partialToolCalls,
        PartialJson $partialJson,
        ?string $maybeToolName,
        string $toolName
    ): ToolCallStreamState {
        if (!$maybeToolName) {
            return $partialToolCalls;
        }
        $buffered = match (true) {
            $partialJson->isEmpty() => null,
            default => $partialJson->normalized(),
        };
        $newPartialToolCalls = $partialToolCalls->handleSignal($maybeToolName, $buffered, $toolName);

        return $newPartialToolCalls;
    }

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
    ): mixed {
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
