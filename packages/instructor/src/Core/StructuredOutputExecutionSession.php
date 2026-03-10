<?php declare(strict_types=1);

namespace Cognesy\Instructor\Core;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Instructor\Contracts\CanEmitStreamingUpdates;
use Cognesy\Instructor\Creation\ExecutionDriverFactory;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputResponse;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseGenerated;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputStarted;
use Cognesy\Instructor\StructuredOutputStream;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use RuntimeException;

final class StructuredOutputExecutionSession
{
    private StructuredOutputExecution $execution;
    private ?CanEmitStreamingUpdates $executionDriver = null;
    private ?StructuredOutputStream $cachedStream = null;

    public function __construct(
        StructuredOutputExecution $execution,
        private readonly ExecutionDriverFactory $executionDriverFactory,
        private readonly CanHandleEvents $events,
    ) {
        $this->execution = $execution;
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

        $this->events->dispatch(new StructuredOutputStarted(['request' => $this->execution->request()->toArray()]));

        $driver = $this->executionDriver();
        while ($driver->hasNextEmission()) {
            $driver->nextEmission();
        }
        $this->execution = $driver->execution();

        $response = $this->execution->inferenceResponse();
        if ($response === null) {
            throw new RuntimeException('Failed to get inference response');
        }

        $this->events->dispatch(new StructuredOutputResponseGenerated([
            'response' => StructuredOutputResponse::final(
                value: $this->execution->output(),
                inferenceResponse: $response,
            ),
        ]));

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

    private function executionDriver(): CanEmitStreamingUpdates
    {
        if ($this->executionDriver !== null) {
            return $this->executionDriver;
        }

        $this->executionDriver = $this->executionDriverFactory->makeExecutionDriver($this->execution);

        return $this->executionDriver;
    }
}
