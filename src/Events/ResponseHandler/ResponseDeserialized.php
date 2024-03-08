<?php

namespace Cognesy\Instructor\Events\ResponseHandler;

use Cognesy\Instructor\Events\Event;

class ResponseDeserialized extends Event
{
    public function __construct(
        public mixed $object
    )
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return json_encode($this->object);
    }
}