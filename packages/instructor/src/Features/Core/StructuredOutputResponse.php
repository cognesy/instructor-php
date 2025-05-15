<?php

namespace Cognesy\Instructor\Features\Core;

use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Events\Instructor\InstructorDone;
use Cognesy\Polyglot\LLM\Data\LLMResponse;
use Cognesy\Utils\Events\EventDispatcher;
use Exception;
use Generator;

class StructuredOutputResponse
{
    private RequestHandler $requestHandler;
    private EventDispatcher $events;
    private StructuredOutputRequest $request;

    public function __construct(
        StructuredOutputRequest $request,
        RequestHandler          $requestHandler,
        EventDispatcher         $events,
    ) {
        $this->events = $events;
        $this->requestHandler = $requestHandler;
        $this->request = $request;
    }

    /**
     * Executes the request and returns the response
     */
    public function get() : mixed {
        if ($this->request->isStreamed()) {
            return $this->stream()->final();
        }
        $response = $this->getResponse();
        $this->events->dispatch(new InstructorDone(['result' => $response]));
        return $response->value();
    }

    /**
     * Executes the request and returns LLM response object
     */
    public function response() : LLMResponse {
        $response = $this->getResponse();
        $this->events->dispatch(new InstructorDone(['result' => $response->value()]));
        return $response;
    }

    /**
     * Executes the request and returns the response stream
     */
    public function stream() : StructuredOutputStream {
        // TODO: do we need this? cannot we just turn streaming on?
        if (!$this->request->isStreamed()) {
            throw new Exception('StructuredOutput::create()->stream() method requires response streaming: set "stream" = true in the request options.');
        }
        $stream = $this->getStream();
        return new StructuredOutputStream($stream, $this->events);
    }

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
        if (is_int($result)) {
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
        if (is_float($result)) {
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
        if (is_string($result)) {
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
     * @template T
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

    // INTERNAL /////////////////////////////////////////////////

    private function getResponse() : LLMResponse {
        // TODO: this should be called only once and result stored
        return $this->requestHandler->responseFor($this->request);
    }

    private function getStream() : Generator {
        // TODO: this should be called only once and result stored
        return $this->requestHandler->streamResponseFor($this->request);
    }
}