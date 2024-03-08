<?php

namespace Cognesy\Instructor\Events\ResponseHandler;

use Cognesy\Instructor\Events\Event;

class ResponseValidationAttempt extends Event
{
    public function __construct(
        public object $response
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return json_encode($this->response);
    }
}