<?php

namespace Cognesy\Instructor\Events\Response;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;
use Cognesy\Instructor\Validation\Contracts\CanValidateSelf;

class CustomResponseValidationAttempt extends Event
{
    public function __construct(
        public CanValidateSelf $response
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return Json::encode($this->response);
    }
}