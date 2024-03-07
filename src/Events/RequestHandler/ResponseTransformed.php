<?php

namespace Cognesy\Instructor\Events\RequestHandler;

use Cognesy\Instructor\Events\Event;

class ResponseTransformed extends Event
{
    public function __construct(
        public mixed $result
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return json_encode($this->result);
    }
}
