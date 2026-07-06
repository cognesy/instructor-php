<?php declare(strict_types=1);

namespace Cognesy\Instructor;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Instructor\Creation\ExecutionDriverFactory;
use Cognesy\Instructor\Core\StructuredOutputExecutionSession;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputResponse;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseGenerated;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Utils\Json\Json;
use Exception;

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

    // TYPECASTED RESULT ACCESS /////////////////////////////////
    // TYPECASTING RESULTS //////////////////////////////////////

    /**
     * Returns the result as a boolean.
     *
     * @return bool
     * @throws Exception
     */
    public function getBoolean() : bool {
        $result = $this->get();
        if (!is_bool($result)) {
            throw new Exception('Result is not a boolean: ' . gettype($result));
        }
        return $result;
    }

    /**
     * Returns the result as an integer.
     *
     * @return int
     * @throws Exception
     */
    public function getInt() : int {
        $result = $this->get();
        if (!is_int($result)) {
            throw new Exception('Result is not an integer: ' . gettype($result));
        }
        return $result;
    }

    /**
     * Returns the result as a float.
     *
     * @return float
     * @throws Exception
     */
    public function getFloat() : float {
        $result = $this->get();
        if (!is_float($result)) {
            throw new Exception('Result is not a float: ' . gettype($result));
        }
        return $result;
    }

    /**
     * Returns the result as a string.
     *
     * @return string
     * @throws Exception
     */
    public function getString() : string {
        $result = $this->get();
        if (!is_string($result)) {
            throw new Exception('Result is not a string: ' . gettype($result));
        }
        return $result;
    }

    /**
     * Returns the result as an array.
     *
     * @return array
     * @throws Exception
     */
    public function getArray() : array {
        $result = $this->get();
        if (!is_array($result)) {
            throw new Exception('Result is not an array: ' . gettype($result));
        }
        return $result;
    }

    /**
     * Returns the result as an object.
     *
     * @return object
     * @throws Exception
     */
    public function getObject() : object {
        $result = $this->get();
        if (!is_object($result)) {
            throw new Exception('Result is not an object: ' . gettype($result));
        }
        return $result;
    }

    /**
     * Returns the result as an instance of the specified class.
     *
     * @template T of object
     * @param class-string<T> $class The class name of the returned object
     * @return T
     * @psalm-return T
     * @throws Exception
     */
    public function getInstanceOf(string $class) : object {
        $result = $this->get();
        if (!is_object($result)) {
            throw new Exception('Result is not an object: ' . gettype($result));
        }
        if (!is_a($result, $class)) {
            throw new Exception('Cannot return type `' . gettype($result) . '` as an instance of: ' . $class);
        }
        return $result;
    }
}
