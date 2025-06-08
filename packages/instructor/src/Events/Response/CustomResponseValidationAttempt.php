<?php

namespace Cognesy\Instructor\Events\Response;

use Cognesy\Events\Event;
use Cognesy\Instructor\Validation\Contracts\CanValidateSelf;
use Cognesy\Utils\Json\Json;

final class CustomResponseValidationAttempt extends Event
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