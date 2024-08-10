<?php

namespace Cognesy\Instructor\Events\Request;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json\Json;
use Psr\Log\LogLevel;

class ValidationRecoveryLimitReached extends Event
{
    public $logLevel = LogLevel::ERROR;

    public function __construct(
        public int $retries,
        public array $errors,
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return Json::encode([
            'retries' => $this->retries,
            'errors' => $this->errors
        ]);
    }
}
