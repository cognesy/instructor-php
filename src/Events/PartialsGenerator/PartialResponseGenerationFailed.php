<?php

namespace Cognesy\Instructor\Events\PartialsGenerator;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json\Json;
use Psr\Log\LogLevel;

class PartialResponseGenerationFailed extends Event
{
    public $logLevel = LogLevel::WARNING;

    public function __construct(public array $errors)
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return Json::encode($this->errors);
    }
}