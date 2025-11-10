<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\Partials;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\ResponseIterators\Partials\DeltaExtraction\ExtractDelta;
use Cognesy\Instructor\ResponseIterators\Partials\JsonMode\AssembleJson;
use Cognesy\Instructor\ResponseIterators\Partials\PartialCreation\DeserializeAndDeduplicate;
use Cognesy\Instructor\ResponseIterators\Partials\PartialCreation\PartialAssembler;
use Cognesy\Instructor\ResponseIterators\Partials\PartialEmission\EnrichResponse;
use Cognesy\Instructor\ResponseIterators\Partials\ResponseAggregation\AggregateResponse;
use Cognesy\Instructor\ResponseIterators\Partials\Sequence\UpdateSequence;
use Cognesy\Instructor\ResponseIterators\Partials\ToolCallMode\HandleToolCallSignals;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Instructor\Validation\Contracts\CanValidatePartialResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Stream\Transformation;
use Cognesy\Stream\TransformationStream;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Factory for creating mode-specific partial processing pipelines.
 * Encapsulates all configuration and wiring.
 */
final readonly class PartialStreamFactory
{
    private CanDeserializeResponse $deserializer;
    private CanValidatePartialResponse $validator;
    private CanTransformResponse $transformer;
    private EventDispatcherInterface $events;

    public function __construct(
        CanDeserializeResponse $deserializer,
        CanValidatePartialResponse $validator,
        CanTransformResponse $transformer,
        EventDispatcherInterface $events,
    ) {
        $this->deserializer = $deserializer;
        $this->validator = $validator;
        $this->transformer = $transformer;
        $this->events = $events;
    }

    // FACTORY METHODS /////////////////////////////////////////////////

    /**
     * Create pure transformation stream (no events).
     * Returns: TransformationStream<AggregatedResponse<T>>
     */
    public function makePureStream(
        iterable $source,
        ResponseModel $responseModel,
        OutputMode $mode,
        bool $accumulatePartials = false,
    ): TransformationStream {
        $pipeline = match($mode) {
            OutputMode::Tools => $this->createToolsModePipeline($responseModel, $mode, $accumulatePartials),
            default => $this->createContentModePipeline($responseModel, $mode, $accumulatePartials),
        };

        return TransformationStream::from($source)->using($pipeline);
    }

    /**
     * Create stream with event decoration.
     * Returns: EventDispatchingStream<AggregatedResponse<T>>
     */
    public function makeObservableStream(
        iterable $source,
        ResponseModel $responseModel,
        OutputMode $mode,
        bool $accumulatePartials = false,
    ): EventDispatchingStream {
        $pureStream = $this->makePureStream($source, $responseModel, $mode, $accumulatePartials);
        return $this->withEvents($pureStream);
    }

    // INTERNAL ///////////////////////////////////////////////////////////

    /**
     * Wrap stream with policy-aware event dispatching.
     */
    private function withEvents(iterable $stream): EventDispatchingStream {
        return new EventDispatchingStream($stream, $this->events);
    }

    /**
     * Content mode: JSON in content field.
     */
    private function createContentModePipeline(ResponseModel $responseModel, OutputMode $mode, bool $accumulatePartials): Transformation {
        $stages = [
            new ExtractDelta($mode),
            new AssembleJson(),
            new DeserializeAndDeduplicate($this->createAssembler(), $responseModel),
            new UpdateSequence($this->events),
            new EnrichResponse(),
        ];
        $stages[] = new AggregateResponse($accumulatePartials);
        return Transformation::define(...$stages);
    }

    /**
     * Tools mode: JSON in tool call arguments.
     */
    private function createToolsModePipeline(ResponseModel $responseModel, OutputMode $mode, bool $accumulatePartials): Transformation {
        $stages = [
            new HandleToolCallSignals($responseModel->toolName(), $this->events),
            new ExtractDelta($mode),
            new AssembleJson(),
            new DeserializeAndDeduplicate($this->createAssembler(), $responseModel),
            new UpdateSequence($this->events),
            new EnrichResponse(),
        ];
        $stages[] = new AggregateResponse($accumulatePartials);
        return Transformation::define(...$stages);
    }

    private function createAssembler(): PartialAssembler {
        return new PartialAssembler(
            deserializer: $this->deserializer,
            validator: $this->validator,
            transformer: $this->transformer,
        );
    }
}
