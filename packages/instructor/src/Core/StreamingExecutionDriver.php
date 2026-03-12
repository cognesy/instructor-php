<?php declare(strict_types=1);

namespace Cognesy\Instructor\Core;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Instructor\Contracts\CanDetermineRetry;
use Cognesy\Instructor\Contracts\CanEmitStreamingUpdates;
use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputResponse;
use Cognesy\Instructor\Streaming\EmissionSnapshot;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Streaming\EmissionFingerprint;
use Cognesy\Instructor\Streaming\Pipeline\AccumulatePartialResponses;
use Cognesy\Instructor\Streaming\Pipeline\DispatchStreamingEvents;
use Cognesy\Instructor\Streaming\StructuredOutputStreamState;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Stream\Transformation;
use Cognesy\Stream\TransformationStream;
use Iterator;
use IteratorAggregate;

final class StreamingExecutionDriver implements CanEmitStreamingUpdates
{
    private ExecutionLoop $loop;
    private ?Iterator $stream = null;
    private ?InferenceResponse $finalInference = null;
    private mixed $finalValue = null;
    private readonly AttemptProcessor $attemptProcessor;
    private EmissionFingerprint $fingerprint;

    public function __construct(
        StructuredOutputExecution $execution,
        private readonly InferenceProvider $inferenceProvider,
        private readonly CanDeserializeResponse $deserializer,
        private readonly CanTransformResponse $transformer,
        CanGenerateResponse $responseGenerator,
        CanDetermineRetry $retryPolicy,
        private readonly CanHandleEvents $events,
    ) {
        $this->loop = new ExecutionLoop($execution);
        $this->fingerprint = EmissionFingerprint::fresh();
        $this->attemptProcessor = new AttemptProcessor(
            responseGenerator: $responseGenerator,
            retryPolicy: $retryPolicy,
        );
    }

    #[\Override]
    public function hasNextEmission(): bool {
        return $this->loop->hasNextEmission($this->advance(...));
    }

    #[\Override]
    public function nextEmission(): ?StructuredOutputResponse {
        return $this->loop->nextEmission($this->advance(...));
    }

    #[\Override]
    public function execution(): StructuredOutputExecution {
        return $this->loop->execution();
    }

    private function advance(ExecutionLoop $loop): void {
        if ($this->stream === null) {
            $this->startAttempt($loop);
        }

        if ($this->stream === null) {
            return;
        }

        if (!$this->stream->valid()) {
            $this->finalizeAttempt($loop);
            return;
        }

        $currentState = $this->stream->current();
        $snapshot = $currentState->snapshot();
        $response = $currentState->partialResponse();
        // Capture finalization data before next() mutates the shared state object
        $this->finalInference = $currentState->finalInferenceResponse();
        $this->finalValue = $currentState->value();
        $this->stream->next();

        if (!$this->stream->valid()) {
            $this->finalizeAttempt($loop);
            return;
        }

        if (!$this->fingerprint->hasChanged($snapshot, $loop->execution()->outputMode())) {
            return;
        }

        $this->fingerprint->remember($snapshot, $loop->execution()->outputMode());
        $loop->emit($response);
    }

    private function startAttempt(ExecutionLoop $loop): void {
        if ($loop->shouldStopAttempts()) {
            $loop->terminate();
            return;
        }

        $execution = $loop->execution()->withStartedAttempt();
        $loop->replaceExecution($execution);

        $responseModel = $execution->responseModel();
        assert($responseModel !== null, 'Response model cannot be null');

        $inferenceStream = $this->inferenceProvider
            ->getInference($execution)
            ->stream()
            ->deltas();

        $aggregateStream = $this->makeStream(
            source: $inferenceStream,
            responseModel: $responseModel,
            mode: $execution->outputMode(),
        );

        $this->stream = $aggregateStream instanceof IteratorAggregate
            ? $aggregateStream->getIterator()
            : $aggregateStream;
        $this->finalInference = null;
        $this->finalValue = null;
        $this->fingerprint = EmissionFingerprint::fresh();
    }

    private function finalizeAttempt(ExecutionLoop $loop): void {
        $result = $this->attemptProcessor->process(
            execution: $loop->execution(),
            inferenceResponse: $this->finalInference ?? new InferenceResponse(),
            prebuiltValue: $this->finalValue,
        );
        $loop->applyAttemptResult($result);
        $this->stream = null;

        if ($result->shouldRetry()) {
            $this->resetAttemptRuntime();
        }
    }

    private function resetAttemptRuntime(): void {
        $this->stream = null;
        $this->finalInference = null;
        $this->finalValue = null;
        $this->fingerprint = EmissionFingerprint::fresh();
    }

    /**
     * @param iterable<PartialInferenceDelta> $source
     * @return IteratorAggregate<int, StructuredOutputStreamState>
     */
    private function makeStream(
        iterable $source,
        ResponseModel $responseModel,
        OutputMode $mode,
    ): IteratorAggregate {
        $stages = [
            new AccumulatePartialResponses(
                mode: $mode,
                deserializer: $this->deserializer,
                transformer: $this->transformer,
                responseModel: $responseModel,
                materializationInterval: $responseModel->config()->streamMaterializationInterval(),
            ),
            new DispatchStreamingEvents(
                events: $this->events,
                expectedToolName: $mode === OutputMode::Tools ? $responseModel->toolName() : '',
            ),
        ];

        $transformation = Transformation::define(...$stages);

        return TransformationStream::from($source)->using($transformation);
    }
}
