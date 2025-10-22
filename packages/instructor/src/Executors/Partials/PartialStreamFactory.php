<?php declare(strict_types=1);

namespace Cognesy\Instructor\Executors\Partials;

use Cognesy\Instructor\Config\PartialsGeneratorConfig;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Executors\Partials\ContentMode\AssembleJson;
use Cognesy\Instructor\Executors\Partials\DeltaExtraction\ExtractDelta;
use Cognesy\Instructor\Executors\Partials\EventDispatching\EventDispatchingStream;
use Cognesy\Instructor\Executors\Partials\PartialCreation\DeserializeAndDeduplicate;
use Cognesy\Instructor\Executors\Partials\PartialCreation\PartialAssembler;
use Cognesy\Instructor\Executors\Partials\PartialEmission\EnrichResponse;
use Cognesy\Instructor\Executors\Partials\ResponseAggregation\AggregateResponse;
use Cognesy\Instructor\Executors\Partials\Sequence\UpdateSequence;
use Cognesy\Instructor\Executors\Partials\ToolCallMode\HandleToolCallSignals;
use Cognesy\Instructor\Transformation\ResponseTransformer;
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
    public function __construct(
        private ResponseDeserializer $deserializer,
        private ResponseTransformer $transformer,
        private EventDispatcherInterface $events,
        private PartialsGeneratorConfig $config,
    ) {}

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
            transformer: $this->transformer,
            config: $this->config,
        );
    }
}
