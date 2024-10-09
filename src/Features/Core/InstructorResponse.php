<?php

namespace Cognesy\Instructor\Features\Core;

use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Instructor\InstructorDone;
use Cognesy\Instructor\Features\Core\Data\Request;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
use Exception;
use Generator;

class InstructorResponse
{
    private RequestHandler $requestHandler;
    private EventDispatcher $events;
    private Request $request;

    public function __construct(
        Request $request,
        RequestHandler $requestHandler,
        EventDispatcher $events,
    ) {
        $this->events = $events;
        $this->requestHandler = $requestHandler;
        $this->request = $request;
    }

    /**
     * Executes the request and returns the response
     */
    public function get() : mixed {
        if ($this->request->isStream()) {
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
    public function stream() : InstructorStream {
        // TODO: do we need this? cannot we just turn streaming on?
        if (!$this->request->isStream()) {
            throw new Exception('Instructor::stream() method requires response streaming: set "stream" = true in the request options.');
        }
        $stream = $this->getStream();
        return new InstructorStream($stream, $this->events);
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