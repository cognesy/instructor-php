<?php

namespace Cognesy\Instructor\Events\Response;

use Cognesy\Events\Event;
use Cognesy\Instructor\Validation\ValidationResult;
use Cognesy\Utils\Json\Json;
use Psr\Log\LogLevel;

final class ResponseValidated extends Event
{
    public $logLevel = LogLevel::INFO;

    public function __construct(
        public ValidationResult $validationResult
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return Json::encode($this->toArray());
    }

    public function toArray(): array {
        return [
            'validationResult' => $this->validationResult->toArray()
        ];
    }
}