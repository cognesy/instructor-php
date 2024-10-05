<?php

namespace Cognesy\Instructor;

use Cognesy\Instructor\Core\RequestHandler;
use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Instructor\InstructorDone;
use Exception;

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
        $result = $this->requestHandler->responseFor($this->request);
        $this->events->dispatch(new InstructorDone(['result' => $result]));
        return $result->value();
    }

    /**
     * Executes the request and returns the response stream
     */
    public function stream() : Stream {
        // TODO: do we need this? cannot we just turn streaming on?
        if (!$this->request->isStream()) {
            throw new Exception('Instructor::stream() method requires response streaming: set "stream" = true in the request options.');
        }
        $stream = $this->requestHandler->streamResponseFor($this->request);
        return new Stream($stream, $this->events);
    }
}