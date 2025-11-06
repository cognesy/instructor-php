<?php declare(strict_types=1);

namespace Cognesy\Instructor;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Instructor\Contracts\CanHandleStructuredOutputAttempts;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseGenerated;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputStarted;
use Cognesy\Instructor\Traits\HandlesResultTypecasting;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Utils\Json\Json;
use RuntimeException;

/**
 * @template TResponse
 */
class PendingStructuredOutput
{
    use HandlesResultTypecasting;

    private readonly CanHandleEvents $events;
    private readonly CanHandleStructuredOutputAttempts $attemptHandler;
    private readonly ExecutorFactory $executorFactory;
    private StructuredOutputExecution $execution;
    private readonly bool $cacheProcessedResponse;
    private ?InferenceResponse $cachedResponse = null;

    public function __construct(
        StructuredOutputExecution $execution,
        ExecutorFactory $executorFactory,
        CanHandleEvents $events,
    ) {
        $this->cacheProcessedResponse = true;
        $this->execution = $execution;
        $this->executorFactory = $executorFactory;
        $this->attemptHandler = $executorFactory->makeExecutor($execution);
        $this->events = $events;
    }

    /**
     * Executes the request and returns the parsed value
     *
     * @return TResponse
     */
    public function get() : mixed {
        return match(true) {
            $this->execution->isStreamed() => $this->stream()->finalValue(),
            default => $this->getResponse()->value(),
        };
    }

    public function toJsonObject() : Json {
        return match(true) {
            $this->execution->isStreamed() => $this->stream()->finalResponse()->findJsonData($this->execution->outputMode()),
            default => $this->getResponse()->findJsonData($this->execution->outputMode())
        };
    }

    public function toJson() : string {
        return $this->toJsonObject()->toString();
    }

    public function toArray() : array {
        return $this->toJsonObject()->toArray();
    }

    /**
     * Executes the request and returns LLM response object
     */
    public function response() : InferenceResponse {
        return $this->getResponse();
    }

    public function execution() : StructuredOutputExecution {
        return $this->execution;
    }

    /**
     * Executes the request and returns the response stream
     *
     * @return StructuredOutputStream<TResponse>
     */
    public function stream() : StructuredOutputStream {
        $this->execution = $this->execution->withStreamed();
        $handler = $this->executorFactory->makeExecutor($this->execution);
        return new StructuredOutputStream($this->execution, $handler, $this->events);
    }

    // INTERNAL /////////////////////////////////////////////////

    private function getResponse() : InferenceResponse {
        $this->events->dispatch(new StructuredOutputStarted(['request' => $this->execution->request()->toArray()]));

        // RESPONSE CACHING = IS DISABLED
        if (!$this->cacheProcessedResponse) {
            while ($this->attemptHandler->hasNext($this->execution)) {
                $this->execution = $this->attemptHandler->nextUpdate($this->execution);
            }
            $response = $this->execution->inferenceResponse();
            if ($response === null) {
                throw new RuntimeException('Failed to get inference response');
            }
            $this->events->dispatch(new StructuredOutputResponseGenerated(['value' => json_encode($response->value())]));
            return $response;
        }

        // RESPONSE CACHING = IS ENABLED
        if ($this->cachedResponse === null) {
            while ($this->attemptHandler->hasNext($this->execution)) {
                $this->execution = $this->attemptHandler->nextUpdate($this->execution);
            }
            $this->cachedResponse = $this->execution->inferenceResponse();
            if ($this->cachedResponse === null) {
                throw new RuntimeException('Failed to get inference response');
            }
        }

        $this->events->dispatch(new StructuredOutputResponseGenerated([
            'value' => json_encode($this->cachedResponse->value()),
            'cached' => true,
        ]));
        return $this->cachedResponse;
    }
}
