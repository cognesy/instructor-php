<?php

namespace Cognesy\Instructor\Events\Response;

use Cognesy\Instructor\Data\ValidationResult;
use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;

class ResponseValidated extends Event
{
    public function __construct(
        public ValidationResult $validationResult
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return Json::encode($this->validationResult);
    }
}