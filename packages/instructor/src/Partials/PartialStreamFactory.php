<?php declare(strict_types=1);

namespace Cognesy\Instructor\Partials;

use Cognesy\Instructor\Config\PartialsGeneratorConfig;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Partials\Events\EventDispatchingStream;
use Cognesy\Instructor\Partials\Events\EventDispatchPolicy;
use Cognesy\Instructor\Partials\Transducers\AggregateResponse;
use Cognesy\Instructor\Partials\ContentMode\AssembleJson;
use Cognesy\Instructor\Partials\Transducers\DeserializeAndDeduplicate;
use Cognesy\Instructor\Partials\Transducers\EnrichResponse;
use Cognesy\Instructor\Partials\Transducers\ExtractDelta;
use Cognesy\Instructor\Partials\ToolCallMode\HandleToolCallSignals;
use Cognesy\Instructor\Partials\Transducers\SequenceUpdates;
use Cognesy\Instructor\Partials\ToolCallMode\ToolCallToJson;
use Cognesy\Instructor\Streaming\PartialGen\AssemblePartialObject;
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
        private EventDispatchPolicy $eventPolicy = new EventDispatchPolicy(),
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
    ): TransformationStream {
        $pipeline = match($mode) {
            OutputMode::Tools => $this->createToolsModePipeline($responseModel, $mode),
            default => $this->createContentModePipeline($responseModel, $mode),
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
    ): EventDispatchingStream {
        $pureStream = $this->makePureStream($source, $responseModel, $mode);
        return $this->withEvents($pureStream);
    }

    // INTERNAL ///////////////////////////////////////////////////////////

    /**
     * Wrap stream with policy-aware event dispatching.
     */
    private function withEvents(iterable $stream): EventDispatchingStream {
        return new EventDispatchingStream($stream, $this->events, $this->eventPolicy);
    }

    /**
     * Content mode: JSON in content field.
     */
    private function createContentModePipeline(ResponseModel $responseModel, OutputMode $mode): Transformation {
        return Transformation::define(
            new ExtractDelta($mode),
            new AssembleJson(),
            new DeserializeAndDeduplicate($this->createAssembler(), $responseModel),
            new SequenceUpdates($this->events),
            new EnrichResponse(),
            new AggregateResponse(),
        );
    }

    /**
     * Tools mode: JSON in tool call arguments.
     */
    private function createToolsModePipeline(ResponseModel $responseModel, OutputMode $mode): Transformation {
        return Transformation::define(
            new HandleToolCallSignals($responseModel->toolName(), $this->events),
            new ExtractDelta($mode),
            // Assemble JSON directly from tool argument deltas
            new AssembleJson(),
            new DeserializeAndDeduplicate($this->createAssembler(), $responseModel),
            new SequenceUpdates($this->events),
            new EnrichResponse(),
            new AggregateResponse(),
        );
    }

    private function createAssembler(): AssemblePartialObject {
        return new AssemblePartialObject(
            deserializer: $this->deserializer,
            transformer: $this->transformer,
            config: $this->config,
        );
    }
}
