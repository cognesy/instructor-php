<?php

namespace Cognesy\Instructor\Events\Response;

use Cognesy\Events\Event;
use Cognesy\Utils\Json\Json;
use Psr\Log\LogLevel;

final class ResponseTransformed extends Event
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
