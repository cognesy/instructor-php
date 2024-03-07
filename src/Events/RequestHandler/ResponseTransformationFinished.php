<?php

namespace Cognesy\Instructor\Events\RequestHandler;

use Cognesy\Instructor\Events\Event;

class ResponseTransformationFinished extends Event
{
    public function __construct(
        public mixed $result
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return $this->format(json_encode($this->result));
    }
}
