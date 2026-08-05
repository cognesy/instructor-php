<?php declare(strict_types=1);

namespace Cognesy\Instructor\Core;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Instructor\Contracts\CanDriveExecution;
use Cognesy\Instructor\Creation\ExecutionDriverFactory;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputResponse;
use Cognesy\Instructor\Telemetry\StructuredOutputEventProjector;
use Cognesy\Instructor\StructuredOutputStream;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use RuntimeException;

final class StructuredOutputExecutionSession
{
    private StructuredOutputExecution $execution;
    private ?CanDriveExecution $executionDriver = null;
    private ?StructuredOutputStream $cachedStream = null;

    /**
     * Payload construction and listener gating both live in the projector. It is built here,
     * per execution, because `PendingStructuredOutput` constructs this session long after any
     * `onEvent()` or `wiretap()` call on the runtime — so the gates it resolves are complete.
     */
    private readonly StructuredOutputEventProjector $projector;

    public function __construct(
        StructuredOutputExecution $execution,
        private readonly ExecutionDriverFactory $executionDriverFactory,
        private readonly CanHandleEvents $events,
    ) {
        $this->execution = $execution;
        $this->projector = new StructuredOutputEventProjector($events);
    }

    public function output(): mixed
    {
        if ($this->execution->hasOutput()) {
            return $this->execution->output();
        }

        $this->inferenceResponse();

        return $this->execution->output();
    }

    public function inferenceResponse(): InferenceResponse
    {
        $existingResponse = $this->execution->inferenceResponse();
        if ($existingResponse !== null) {
            return $existingResponse;
        }

        if ($this->execution->isStreamed() || $this->cachedStream !== null) {
            return $this->stream()->finalInferenceResponse();
        }

        $this->projector->started($this->execution);

        $driver = $this->executionDriver();
        while ($driver->hasNextEmission()) {
            $driver->nextEmission();
        }
        $this->execution = $driver->execution();

        $response = $this->execution->inferenceResponse();
        if ($response === null) {
            throw new RuntimeException('Failed to get inference response');
        }

        $this->projector->generated(
            StructuredOutputResponse::final(
                value: $this->execution->output(),
                inferenceResponse: $response,
            ),
            $this->execution,
        );

        return $response;
    }

    public function stream(): StructuredOutputStream
    {
        if ($this->cachedStream !== null) {
            return $this->cachedStream;
        }

        $this->execution = $this->execution->withStreamed();
        $this->cachedStream = new StructuredOutputStream(
            $this->execution,
            $this->executionDriverFactory->makeStreamingExecutionDriver($this->execution),
            $this->events,
        );

        return $this->cachedStream;
    }

    public function execution(): StructuredOutputExecution
    {
        return $this->execution;
    }

    private function executionDriver(): CanDriveExecution
    {
        if ($this->executionDriver !== null) {
            return $this->executionDriver;
        }

        $this->executionDriver = $this->executionDriverFactory->makeExecutionDriver($this->execution);

        return $this->executionDriver;
    }
}
