<?php

namespace Cognesy\Instructor\Events\Instructor;

use Cognesy\Utils\Events\Event;
use Cognesy\Utils\Json\Json;
use Psr\Log\LogLevel;

class InstructorDone extends Event
{
    public $logLevel = LogLevel::INFO;

    public function __construct(
        mixed $data
    ) {
        parent::__construct($data);
    }

    public function __toString(): string {
        return Json::encode($this->data);
    }
}
