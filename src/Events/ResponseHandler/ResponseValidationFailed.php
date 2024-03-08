<?php

namespace Cognesy\Instructor\Events\ResponseHandler;

use Cognesy\Instructor\Events\Event;

class ResponseValidationFailed extends Event
{
    public function __construct(
        public array $errors
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return json_encode($this->errors);
    }
}