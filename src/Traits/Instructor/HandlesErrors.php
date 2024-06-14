<?php

namespace Cognesy\Instructor\Traits\Instructor;

use Cognesy\Instructor\Events\Instructor\ErrorRaised;
use Cognesy\Instructor\Events\Instructor\InstructorDone;
use Throwable;

trait HandlesErrors
{
    /** @var callable|null */
    protected $onError;

    /**
     * Listens to Instructor execution error
     *
     * @param-later-invoked-callable $listener
     */
    public function onError(callable $listener) : self {
        $this->onError = $listener;
        return $this;
    }

    // INTERNAL ////////////////////////////////////////////////////////////////////

    protected function handleError(Throwable $error) : mixed {
        // TODO: rethink this
        // if anything goes wrong, we first dispatch an event (e.g. to log error)
        $event = new ErrorRaised($error, $this->requestData);
        $this->events->dispatch($event);
        if (isset($this->onError)) {
            // final attempt to recover from the error (e.g. give fallback response)
            $result = ($this->onError)($event);
            if (!($result instanceof Throwable)) {
                return $result;
            }
        }
        $this->events->dispatch(new InstructorDone(['error' => $error->getMessage()]));
        throw $error;
    }
}