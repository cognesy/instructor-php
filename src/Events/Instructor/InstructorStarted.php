<?php

namespace Cognesy\Instructor\Events\Instructor;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;
use Psr\Log\LogLevel;

class InstructorStarted extends Event
{
    public $logLevel = LogLevel::INFO;

    public function __construct() {
        parent::__construct();
    }

    public function __toString(): string
    {
        return Json::encode($this->config);
    }
}