<?php

namespace Cognesy\Instructor\Traits;

use Cognesy\Instructor\Events\Instructor\ErrorRaised;
use Cognesy\Instructor\Events\Instructor\InstructorDone;
use Throwable;

trait HandlesErrors
{
    protected $onError;

    /**
     * Listens to Instructor execution error
     */
    public function onError(callable $listener) : self {
        $this->onError = $listener;
        return $this;
    }

    protected function handleError(Throwable $error) : mixed {
        // if anything goes wrong, we first dispatch an event (e.g. to log error)
        $event = new ErrorRaised($error, $this->request);
        $this->events->dispatch($event);
        if (isset($this->onError)) {
            // final attempt to recover from the error (e.g. give fallback response)
            return ($this->onError)($event);
        }
        $this->events->dispatch(new InstructorDone());
        throw $error;
    }
}