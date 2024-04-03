<?php

namespace Cognesy\Instructor\Events\ResponseHandler;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;

class ResponseValidationAttempt extends Event
{
    public function __construct(
        public object $response
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return Json::encode($this->response);
    }
}