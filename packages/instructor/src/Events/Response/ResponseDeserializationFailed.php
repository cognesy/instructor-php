<?php

namespace Cognesy\Instructor\Events\Response;

use Cognesy\Utils\Events\Event;
use Psr\Log\LogLevel;

final class ResponseDeserializationFailed extends Event
{
    public $logLevel = LogLevel::WARNING;

    public function __construct(
        public string $errors
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return $this->errors;
    }
}