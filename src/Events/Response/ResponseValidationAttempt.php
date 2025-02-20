<?php

namespace Cognesy\Instructor\Events\Response;

use Cognesy\Utils\Events\Event;
use Cognesy\Utils\Json\Json;

class ResponseValidationAttempt extends Event
{
    public function __construct(
        public object $response
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return Json::encode($this->response);
    }
}