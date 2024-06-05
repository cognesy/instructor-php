<?php

namespace Cognesy\Instructor\Events\Response;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;
use Cognesy\Instructor\Validation\ValidationResult;
use Psr\Log\LogLevel;

class ResponseValidated extends Event
{
    public $logLevel = LogLevel::INFO;

    public function __construct(
        public ValidationResult $validationResult
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return Json::encode($this->validationResult);
    }
}