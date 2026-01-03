<?php declare(strict_types=1);

namespace Cognesy\Instructor;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Instructor\Contracts\CanHandleStructuredOutputAttempts;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseGenerated;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputStarted;
use Cognesy\Instructor\Traits\HandlesResultTypecasting;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;
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
    private readonly ResponseIteratorFactory $executorFactory;
    private StructuredOutputExecution $execution;
    private ?InferenceResponse $cachedResponse = null;
    private ?StructuredOutputStream $cachedStream = null;

    public function __construct(
        StructuredOutputExecution $execution,
        ResponseIteratorFactory $executorFactory,
        CanHandleEvents $events,
    ) {
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
        if ($this->cachedStream !== null) {
            return $this->cachedStream;
        }
        $this->execution = $this->execution->withStreamed();
        $handler = $this->executorFactory->makeExecutor($this->execution);
        $stream = new StructuredOutputStream($this->execution, $handler, $this->events);
        $this->cachedStream = $stream;
        return $stream;
    }

    // INTERNAL /////////////////////////////////////////////////

    private function getResponse() : InferenceResponse {
        $this->events->dispatch(new StructuredOutputStarted(['request' => $this->execution->request()->toArray()]));
        $existingResponse = $this->execution->inferenceResponse();
        if ($existingResponse !== null) {
            return $existingResponse;
        }
        if ($this->shouldCache() && $this->cachedResponse !== null) {
            $this->events->dispatch(new StructuredOutputResponseGenerated([
                'value' => json_encode($this->cachedResponse->value()),
                'cached' => true,
            ]));
            return $this->cachedResponse;
        }

        while ($this->attemptHandler->hasNext($this->execution)) {
            $this->execution = $this->attemptHandler->nextUpdate($this->execution);
        }
        $response = $this->execution->inferenceResponse();
        if ($response === null) {
            throw new RuntimeException('Failed to get inference response');
        }
        if ($this->shouldCache()) {
            $this->cachedResponse = $response;
        }
        $this->events->dispatch(new StructuredOutputResponseGenerated(['value' => json_encode($response->value())]));
        return $response;
    }

    private function shouldCache(): bool {
        return $this->cachePolicy()->shouldCache();
    }

    private function cachePolicy(): ResponseCachePolicy {
        return $this->execution->config()->responseCachePolicy();
    }
}
