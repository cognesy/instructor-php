<?php

namespace Cognesy\Instructor\Events\Response;

use Cognesy\Utils\Events\Event;
use Cognesy\Utils\Json\Json;
use Psr\Log\LogLevel;

final class ResponseGenerationFailed extends Event
{
    public $logLevel = LogLevel::WARNING;

    public function __construct(
        public array $errors,
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return Json::encode($this->errors);
    }
}