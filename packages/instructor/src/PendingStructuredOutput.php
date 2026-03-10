<?php declare(strict_types=1);

namespace Cognesy\Instructor;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Instructor\Creation\ExecutionDriverFactory;
use Cognesy\Instructor\Core\StructuredOutputExecutionSession;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputResponse;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseGenerated;
use Cognesy\Instructor\Traits\HandlesResultTypecasting;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Utils\Json\Json;

/**
 * Public lazy handle for one structured-output operation.
 *
 * Responsibilities:
 * - trigger execution only when result data is requested
 * - coordinate one-shot access across `get()`, `response()`, `inferenceResponse()`, and `stream()`
 * - cache the finalized structured/raw result for repeated reads when allowed
 *
 * Non-responsibilities:
 * - it is not the owner of long-lived streaming state
 * - it is not a generic lifecycle abstraction shared with Polyglot
 * - it should not materialize per-chunk snapshots beyond the dedicated stream/state objects
 *
 * @template TResponse
 */
class PendingStructuredOutput
{
    use HandlesResultTypecasting;

    private readonly StructuredOutputExecutionSession $session;

    public function __construct(
        StructuredOutputExecution $execution,
        ExecutionDriverFactory $executionDriverFactory,
        CanHandleEvents $events,
    ) {
        $this->session = new StructuredOutputExecutionSession(
            execution: $execution,
            executionDriverFactory: $executionDriverFactory,
            events: $events,
        );
    }

    /**
     * Executes the request and returns the parsed value
     *
     * @return TResponse
     */
    public function get() : mixed {
        return match(true) {
            $this->execution()->isStreamed() => $this->stream()->finalValue(),
            default => $this->session->output(),
        };
    }

    public function toJsonObject() : Json {
        return match(true) {
            $this->execution()->isStreamed() => $this->toJsonObjectFromResponse($this->stream()->finalInferenceResponse()),
            default => $this->toJsonObjectFromResponse($this->session->inferenceResponse()),
        };
    }

    public function toJson() : string {
        return $this->toJsonObject()->toString();
    }

    public function toArray() : array {
        return $this->toJsonObject()->toArray();
    }

    /**
     * Executes the request and returns Instructor response object
     */
    public function response() : StructuredOutputResponse {
        return new StructuredOutputResponse(
            value: $this->session->output(),
            inferenceResponse: $this->session->inferenceResponse(),
            isPartial: false,
        );
    }

    public function inferenceResponse() : InferenceResponse {
        return $this->session->inferenceResponse();
    }

    public function execution() : StructuredOutputExecution {
        return $this->session->execution();
    }

    /**
     * Executes the request and returns the response stream
     *
     * @return StructuredOutputStream<TResponse>
     */
    public function stream() : StructuredOutputStream {
        return $this->session->stream();
    }

    private function toJsonObjectFromResponse(InferenceResponse $response) : Json {
        return match ($this->execution()->outputMode()) {
            OutputMode::Tools => $response->findToolCallJsonData(),
            default => $response->findJsonData(),
        };
    }
}
