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

    protected function handleError(Throwable $error) : mixed {
        // if anything goes wrong, we first dispatch an event (e.g. to log error)
        $event = new ErrorRaised($error, $this->getRequest());
        $this->events->dispatch($event);
        if (isset($this->onError)) {
            // final attempt to recover from the error (e.g. give fallback response)
            return ($this->onError)($event);
        }
        $this->events->dispatch(new InstructorDone(['error' => $error->getMessage()]));
        throw $error;
    }
}