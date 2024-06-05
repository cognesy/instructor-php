<?php

namespace Cognesy\Instructor\Events\Response;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;
use Cognesy\Instructor\Validation\ValidationResult;
use Psr\Log\LogLevel;

class ResponseValidationFailed extends Event
{
    public $logLevel = LogLevel::WARNING;

    public function __construct(
        public ValidationResult $validationResult
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return Json::encode([
            'valid' => $this->validationResult->isValid(),
            'errors' => $this->validationResult->getErrorMessage(),
        ]);
    }
}