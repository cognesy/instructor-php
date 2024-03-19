<?php

namespace Cognesy\Instructor\Events\ResponseHandler;

use Cognesy\Instructor\Contracts\CanTransformSelf;
use Cognesy\Instructor\Events\Event;

class ResponseTransformationAttempt extends Event
{
    public function __construct(
        public CanTransformSelf $object
    ) {
        parent::__construct($object);
    }

    public function __toString(): string
    {
        return json_encode($this->object);
    }
}