<?php

namespace Cognesy\Instructor\Events\RequestHandler;

use Cognesy\Instructor\Events\Event;

class ResponseConvertedToObject extends Event
{
    public function __construct(
        public mixed $object
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return json_encode($this->object);
    }
}