<?php

namespace Cognesy\Instructor\Events\Response;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json\Json;
use Psr\Log\LogLevel;

class ResponseTransformed extends Event
{
    public $logLevel = LogLevel::INFO;

    public function __construct(
        public mixed $result
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return Json::encode($this->result);
    }
}
