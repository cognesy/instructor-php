<?php

namespace Cognesy\Instructor\Events\RequestHandler;

use Cognesy\Instructor\Events\Event;

class FunctionCallResponseReceived extends Event
{
    public function __construct(
        public mixed $response
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return json_encode($this->response);
    }
}