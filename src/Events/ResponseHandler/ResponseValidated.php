<?php

namespace Cognesy\Instructor\Events\ResponseHandler;

use Cognesy\Instructor\Data\ValidationResult;
use Cognesy\Instructor\Events\Event;

class ResponseValidated extends Event
{
    public function __construct(
        public ValidationResult $validationResult
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return json_encode($this->validationResult);
    }
}