<?php

namespace Cognesy\Instructor\Events\ResponseHandler;

use Cognesy\Instructor\Contracts\CanSelfValidate;
use Cognesy\Instructor\Events\Event;

class CustomResponseValidationAttempt extends Event
{
    public function __construct(
        public CanSelfValidate $response
    )
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return json_encode($this->response);
    }
}